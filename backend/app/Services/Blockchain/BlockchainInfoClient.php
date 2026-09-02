<?php

namespace App\Services\Blockchain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockchainInfoClient
{
    /**
     * @return array<string, mixed>
     */
    public function getXpubDashboard(string $xpub): array
    {
        $cacheKey = 'blockchain_info.xpub.'.md5($xpub);
        $ttl = config('crypto.mempool.cache_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($xpub): array {
            $receiveChain = $this->fetchSubchain($xpub, 0);
            $changeChain = $this->fetchSubchain($xpub, 1);

            /** @var list<array<string, mixed>> $receiveTxs */
            $receiveTxs = $receiveChain['txs'] ?? [];
            /** @var list<array<string, mixed>> $changeTxs */
            $changeTxs = $changeChain['txs'] ?? [];

            return [
                'txs' => $this->mergeTransactions($receiveTxs, $changeTxs),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSubchain(string $xpub, int $chainIndex): array
    {
        $response = $this->request()->get('/multiaddr', [
            'active' => $xpub.'|'.$chainIndex,
            'n' => 50,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Blockchain.info API request failed: '.$response->status(),
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return $body;
    }

    /**
     * @param  list<array<string, mixed>>  $receiveTxs
     * @param  list<array<string, mixed>>  $changeTxs
     * @return list<array<string, mixed>>
     */
    private function mergeTransactions(array $receiveTxs, array $changeTxs): array
    {
        $merged = [];

        foreach ([...$receiveTxs, ...$changeTxs] as $transaction) {
            $hash = (string) ($transaction['hash'] ?? '');

            if ($hash === '') {
                continue;
            }

            $merged[$hash] = $transaction;
        }

        return array_values($merged);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl('https://blockchain.info')
            ->acceptJson()
            ->timeout(30);
    }
}
