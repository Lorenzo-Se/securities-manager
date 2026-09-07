<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SoChainClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getAddressTransactions(string $network, string $address): array
    {
        $cacheKey = 'sochain.'.md5($network.$address);
        $ttl = (int) config('crypto.sochain.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($network, $address): array {
            return $this->requestFromApi($network, $address);
        });
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestFromApi(string $network, string $address): array
    {
        $encodedNetwork = rawurlencode($network);
        $encodedAddress = rawurlencode($address);

        $response = $this->request()->get("/address/{$encodedNetwork}/{$encodedAddress}");

        if ($response->status() === 429) {
            throw new RuntimeException('SoChain API Rate-Limit erreicht. Bitte später erneut versuchen.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('SoChain API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        if (($body['status'] ?? '') !== 'success') {
            $message = (string) ($body['data'] ?? 'Unbekannter SoChain-Fehler.');

            throw new RuntimeException('SoChain API: '.$message);
        }

        /** @var array<string, mixed> $data */
        $data = $body['data'] ?? [];

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $data['txs'] ?? [];

        return $transactions;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.sochain.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
