<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BlockchainInfoClient
{
    use CachesBlockchainResponses;

    /**
     * @return array<string, mixed>
     */
    public function getXpubDashboard(string $xpub): array
    {
        $cacheKey = 'blockchain_info.xpub.'.md5($xpub);
        $ttl = (int) config('crypto.blockchain_info.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($xpub): array {
            return $this->requestXpubDashboard($xpub);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requestXpubDashboard(string $xpub): array
    {
        $response = $this->request()->get('/multiaddr', [
            'active' => $xpub,
            'n' => 50,
            'format' => 'json',
        ]);

        if ($response->status() === 400) {
            throw $this->buildClientError($xpub, $response);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Blockchain.info API-Anfrage fehlgeschlagen: HTTP '.$response->status(),
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        if (isset($body['error'])) {
            throw $this->buildClientError($xpub, $response, (string) ($body['message'] ?? $body['error']));
        }

        /** @var list<array<string, mixed>> $transactions */
        $transactions = $body['txs'] ?? [];

        return [
            'txs' => $transactions,
        ];
    }

    private function buildClientError(string $xpub, $response, ?string $apiMessage = null): RuntimeException
    {
        if ($apiMessage === null) {
            /** @var array<string, mixed>|null $body */
            $body = $response->json();
            $apiMessage = is_array($body) ? (string) ($body['message'] ?? $body['error'] ?? '') : '';
        }

        Log::info('Blockchain.info xpub sync rejected', [
            'status' => $response->status(),
            'message' => $apiMessage,
            'xpub_prefix' => substr($xpub, 0, 4),
        ]);

        if ($apiMessage !== '' && str_contains(strtolower($apiMessage), 'invalid address')) {
            return new RuntimeException(
                'Bitcoin-xpub konnte nicht abgefragt werden. Prüfe den Extended Public Key oder nutze eine Einzeladresse für den Sync.',
            );
        }

        return new RuntimeException(
            'Blockchain.info API: '.($apiMessage !== '' ? $apiMessage : 'Anfrage fehlgeschlagen (HTTP '.$response->status().').'),
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.blockchain_info.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
