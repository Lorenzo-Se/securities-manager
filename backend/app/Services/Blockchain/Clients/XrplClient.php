<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XrplClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getAccountTransactions(string $address): array
    {
        $cacheKey = 'xrpl.'.md5($address);
        $ttl = (int) config('crypto.xrpl.cache_ttl', 300);

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
        $response = $this->request()->post('', [
            'method' => 'account_tx',
            'params' => [[
                'account' => $address,
                'ledger_index_min' => -1,
                'ledger_index_max' => -1,
                'limit' => 100,
            ]],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('XRPL API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        if (isset($body['result']['error'])) {
            throw new RuntimeException('XRPL API: '.(string) $body['result']['error']);
        }

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $body['result']['transactions'] ?? [];

        return $transactions;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.xrpl.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
