<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HyperliquidClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getUserFills(string $address): array
    {
        $cacheKey = 'hyperliquid.'.md5($address);
        $ttl = (int) config('crypto.hyperliquid.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($address): array {
            return $this->requestFromApi($address);
        });
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestFromApi(string $address): array
    {
        $response = $this->request()->post('/info', [
            'type' => 'userFills',
            'user' => $address,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Hyperliquid API request failed: '.$response->status());
        }

        /** @var list<array<string, mixed>> $fills */
        $fills = $response->json();

        return is_array($fills) ? $fills : [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.hyperliquid.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
