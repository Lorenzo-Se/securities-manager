<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockfrostClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getAddressTransactions(string $address): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Blockfrost API-Schlüssel fehlt. Setze BLOCKFROST_API_KEY in der backend/.env.',
            );
        }

        $cacheKey = 'blockfrost.'.md5($address);
        $ttl = (int) config('crypto.blockfrost.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($address): array {
            return $this->requestFromApi($address);
        });
    }

    public function isConfigured(): bool
    {
        $apiKey = config('crypto.blockfrost.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestFromApi(string $address): array
    {
        $encoded = rawurlencode($address);

        $response = $this->request()->get("/addresses/{$encoded}/transactions", [
            'order' => 'desc',
            'count' => 100,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Blockfrost API request failed: '.$response->status());
        }

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $response->json();

        return is_array($transactions) ? $transactions : [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.blockfrost.base_url'), '/'))
            ->acceptJson()
            ->withHeaders([
                'project_id' => (string) config('crypto.blockfrost.api_key'),
            ])
            ->timeout(30);
    }
}
