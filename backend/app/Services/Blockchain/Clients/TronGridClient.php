<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TronGridClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getNativeTransactions(string $address): array
    {
        return $this->fetchTransactions($address, false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTrc20Transactions(string $address, string $contractAddress): array
    {
        return $this->fetchTransactions($address, true, $contractAddress);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTransactions(string $address, bool $trc20, ?string $contractAddress = null): array
    {
        $cacheKey = 'trongrid.'.md5($address.($trc20 ? 'trc20' : 'native').($contractAddress ?? ''));
        $ttl = (int) config('crypto.trongrid.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($address, $trc20, $contractAddress): array {
            return $this->requestFromApi($address, $trc20, $contractAddress);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestFromApi(string $address, bool $trc20, ?string $contractAddress): array
    {
        $encoded = rawurlencode($address);
        $path = $trc20
            ? "/v1/accounts/{$encoded}/transactions/trc20"
            : "/v1/accounts/{$encoded}/transactions";

        $params = ['limit' => 100];

        if ($trc20 && $contractAddress !== null) {
            $params['contract_address'] = $contractAddress;
        }

        $response = $this->request()->get($path, $params);

        if (! $response->successful()) {
            throw new RuntimeException('TronGrid API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        /** @var list<array<string, mixed>> $data */
        $data = $body['data'] ?? [];

        return $data;
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('crypto.trongrid.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);

        $apiKey = config('crypto.trongrid.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $apiKey]);
        }

        return $request;
    }
}
