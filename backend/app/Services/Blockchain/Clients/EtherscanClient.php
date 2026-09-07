<?php

namespace App\Services\Blockchain\Clients;

use App\Services\Blockchain\Concerns\CachesBlockchainResponses;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EtherscanClient
{
    use CachesBlockchainResponses;

    /**
     * @param  array{base_url: string, api_key: mixed, cache_ttl: int, chain_id?: int}  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

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
        $apiKey = $this->config['api_key'] ?? null;

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTransactions(string $address, string $action, ?string $contractAddress = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Etherscan API-Schlüssel fehlt. Setze ETHERSCAN_API_KEY in der backend/.env.',
            );
        }

        $cacheKey = 'etherscan.'.md5($action.$address.($contractAddress ?? ''));
        $ttl = (int) ($this->config['cache_ttl'] ?? 300);

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
            'apikey' => $this->config['api_key'],
        ];

        if (isset($this->config['chain_id'])) {
            $params['chainid'] = $this->config['chain_id'];
        }

        if ($contractAddress !== null) {
            $params['contractaddress'] = $contractAddress;
        }

        $response = $this->request()->get('', $params);

        if (! $response->successful()) {
            throw new RuntimeException('Etherscan API request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        if (($body['status'] ?? '0') !== '1' && ($body['message'] ?? '') !== 'No transactions found') {
            $message = (string) ($body['result'] ?? $body['message'] ?? 'Unbekannter Etherscan-Fehler.');

            throw new RuntimeException('Etherscan API: '.$message);
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
        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->timeout(30);
    }
}
