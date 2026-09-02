<?php

namespace App\Services\Blockchain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MempoolClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getAddressTransactions(string $address): array
    {
        $cacheKey = 'mempool.address.'.md5($address);
        $ttl = config('crypto.mempool.cache_ttl');

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($address): array {
                return $this->requestAddressTransactions($address);
            });
        } catch (RuntimeException $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestAddressTransactions(string $address): array
    {
        $encoded = rawurlencode($address);
        $response = $this->request()->get("/address/{$encoded}/txs");

        if (! $response->successful()) {
            throw new RuntimeException('Mempool API request failed: '.$response->status());
        }

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $response->json();

        return $transactions;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.mempool.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
