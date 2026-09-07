<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HeliusClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getAddressTransactions(string $address): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Helius API-Schlüssel fehlt. Setze HELIUS_API_KEY in der backend/.env.',
            );
        }

        $cacheKey = 'helius.'.md5($address);
        $ttl = (int) config('crypto.helius.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($address): array {
            return $this->requestFromApi($address);
        });
    }

    public function isConfigured(): bool
    {
        $apiKey = config('crypto.helius.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestFromApi(string $address): array
    {
        $apiKey = config('crypto.helius.api_key');
        $encoded = rawurlencode($address);

        $response = $this->request()->get("/v0/addresses/{$encoded}/transactions", [
            'api-key' => $apiKey,
            'limit' => 100,
        ]);

        if ($response->status() === 429) {
            throw new RuntimeException('Helius API Rate-Limit erreicht. Bitte später erneut versuchen.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Helius API request failed: '.$response->status());
        }

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $response->json();

        return is_array($transactions) ? $transactions : [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.helius.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
