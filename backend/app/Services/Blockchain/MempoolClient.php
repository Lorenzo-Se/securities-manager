<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MempoolClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getAddressTransactions(string $address): array
    {
        return $this->fetchCachedAddressTransactions($address);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collectAddressTransactions(string $address): array
    {
        if (! $this->addressHasActivity($address)) {
            return [];
        }

        return $this->fetchCachedAddressTransactions($address);
    }

    public function addressHasActivity(string $address): bool
    {
        return $this->metaHasActivity($this->getAddressMeta($address));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCachedAddressTransactions(string $address): array
    {
        return $this->fetchCachedAddressTransactions($address);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCachedAddressTransactions(string $address): array
    {
        $cacheKey = 'mempool.address.txs.'.md5($address);
        $ttl = (int) config('crypto.mempool.cache_ttl');
        $staleTtl = (int) config('crypto.mempool.stale_cache_ttl');

        return $this->rememberWithStale($cacheKey, $ttl, function () use ($address): array {
            return $this->requestAddressTransactions($address);
        }, $staleTtl);
    }

    /**
     * @return array<string, mixed>
     */
    private function getAddressMeta(string $address): array
    {
        $cacheKey = 'mempool.address.meta.'.md5($address);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $meta = $this->requestAddressMeta($address);
            $ttl = $this->metaHasActivity($meta)
                ? (int) config('crypto.mempool.cache_ttl')
                : (int) config('crypto.mempool.inactive_address_cache_ttl');

            Cache::put($cacheKey, $meta, $ttl);
            Cache::put($cacheKey.'.stale', $meta, (int) config('crypto.mempool.stale_cache_ttl'));

            return $meta;
        } catch (RuntimeException $exception) {
            $stale = Cache::get($cacheKey.'.stale');

            if (is_array($stale)) {
                Log::warning('Mempool address metadata unavailable, serving stale cache', [
                    'address' => $address,
                    'error' => $exception->getMessage(),
                ]);

                return $stale;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaHasActivity(array $meta): bool
    {
        foreach (['chain_stats', 'mempool_stats'] as $statsKey) {
            $stats = $meta[$statsKey] ?? null;

            if (! is_array($stats)) {
                continue;
            }

            if ((int) ($stats['tx_count'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestAddressMeta(string $address): array
    {
        $encoded = rawurlencode($address);
        $response = $this->httpGet("/address/{$encoded}");

        if ($response->status() === 429) {
            throw new RuntimeException($this->rateLimitMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('Mempool API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return $body;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestAddressTransactions(string $address): array
    {
        $encoded = rawurlencode($address);
        $response = $this->httpGet("/address/{$encoded}/txs");

        if ($response->status() === 429) {
            throw new RuntimeException($this->rateLimitMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('Mempool API request failed: '.$response->status());
        }

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $response->json();

        return $transactions;
    }

    private function httpGet(string $path): Response
    {
        $maxRetries = max(1, (int) config('crypto.mempool.max_retries'));
        $delayMs = max(0, (int) config('crypto.mempool.request_delay_ms'));

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $response = $this->request()->get($path);

            if ($response->status() !== 429 || $attempt === $maxRetries) {
                return $response;
            }

            $retryAfter = (int) ($response->header('Retry-After') ?? 0);
            $backoffMs = max((int) config('crypto.mempool.retry_backoff_ms'), 0);

            if ($retryAfter > 0) {
                usleep($retryAfter * 1_000_000);
            } elseif ($backoffMs > 0) {
                usleep($backoffMs * $attempt * 1000);
            }
        }

        return $this->request()->get($path);
    }

    private function rateLimitMessage(): string
    {
        return 'Mempool API Rate-Limit erreicht (429). Bitte später erneut synchronisieren oder MEMPOOL_BASE_URL auf eine eigene Instanz setzen.';
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.mempool.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
