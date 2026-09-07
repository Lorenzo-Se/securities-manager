<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BscScanClient
{
    use CachesBlockchainResponses;

    /**
     * @return list<array<string, mixed>>
     */
    public function getNativeTransactions(string $address): array
    {
        return $this->fetchTransactions($address, 'txlist');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTokenTransactions(string $address, string $contractAddress): array
    {
        return $this->fetchTransactions($address, 'tokentx', $contractAddress);
    }

    public function isConfigured(): bool
    {
        $apiKey = config('crypto.bscscan.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTransactions(string $address, string $action, ?string $contractAddress = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'BscScan API-Schlüssel fehlt. Setze BSCSCAN_API_KEY in der backend/.env.',
            );
        }

        $cacheKey = 'bscscan.'.md5($action.$address.($contractAddress ?? ''));
        $ttl = (int) config('crypto.bscscan.cache_ttl', 300);

        return $this->remember($cacheKey, $ttl, function () use ($address, $action, $contractAddress): array {
            return $this->requestFromApi($address, $action, $contractAddress);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestFromApi(string $address, string $action, ?string $contractAddress): array
    {
        $params = [
            'module' => 'account',
            'action' => $action,
            'address' => $address,
            'startblock' => 0,
            'endblock' => 99999999,
            'page' => 1,
            'offset' => 100,
            'sort' => 'desc',
            'apikey' => config('crypto.bscscan.api_key'),
        ];

        if ($contractAddress !== null) {
            $params['contractaddress'] = $contractAddress;
        }

        $response = $this->request()->get('', $params);

        if (! $response->successful()) {
            throw new RuntimeException('BscScan API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        if (($body['status'] ?? '0') !== '1' && ($body['message'] ?? '') !== 'No transactions found') {
            $message = (string) ($body['result'] ?? $body['message'] ?? 'Unbekannter BscScan-Fehler.');

            throw new RuntimeException('BscScan API: '.$message);
        }

        /** @var list<array<string, mixed>>|string $result */
        $result = $body['result'] ?? [];

        if (! is_array($result)) {
            return [];
        }

        return $result;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('crypto.bscscan.base_url'), '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
