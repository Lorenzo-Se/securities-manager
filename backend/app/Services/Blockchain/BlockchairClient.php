<?php

namespace App\Services\Blockchain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockchairClient
{
    public function isConfigured(): bool
    {
        $apiKey = config('crypto.blockchair.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getXpubDashboard(string $chainSlug, string $xpub): array
    {
        $encoded = rawurlencode($xpub);

        return $this->fetch("/{$chainSlug}/dashboards/xpub/{$encoded}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getAddressDashboard(string $chainSlug, string $address): array
    {
        $encoded = rawurlencode($address);

        return $this->fetch("/{$chainSlug}/dashboards/address/{$encoded}");
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $path): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Blockchair API-Schlüssel fehlt. Setze BLOCKCHAIR_API_KEY in der .env oder nutze einen unterstützten kostenlosen Provider.',
            );
        }

        $cacheKey = 'blockchair.'.md5($path);
        $ttl = config('crypto.blockchair.cache_ttl');

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($path): array {
                return $this->requestFromApi($path);
            });
        } catch (RuntimeException $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFromApi(string $path): array
    {
        $response = $this->request()->get($path, [
            'limit' => 100,
            'offset' => 0,
        ]);

        if ($response->status() === 430) {
            throw new RuntimeException(
                'Blockchair hat die Anfrage blockiert (430). Bitte BLOCKCHAIR_API_KEY setzen oder einen anderen Sync-Provider nutzen.',
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException('Blockchair API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        if (($body['context']['code'] ?? 500) !== 200) {
            $error = (string) ($body['context']['error'] ?? 'Unbekannter Blockchair-Fehler.');

            throw new RuntimeException('Blockchair API: '.$error);
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('crypto.blockchair.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);

        $apiKey = config('crypto.blockchair.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withQueryParameters(['key' => $apiKey]);
        }

        return $request;
    }
}
