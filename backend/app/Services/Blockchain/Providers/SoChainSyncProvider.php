<?php

namespace App\Services\Blockchain\Providers;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\Clients\SoChainClient;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;
use RuntimeException;

class SoChainSyncProvider implements BlockchainSyncProvider
{
    public function __construct(
        private readonly SoChainClient $client,
        private readonly TransactionMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'sochain';
    }

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool
    {
        return in_array($chain->slug, ['litecoin', 'dogecoin', 'zcash'], true)
            && $walletSourceType === WalletSourceType::Address;
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return list<SyncedTransaction>
     */
    public function fetchTransactions(Portfolio $portfolio, ChainConfig $chain): array
    {
        $network = $chain->providerOption('sochain_network');

        if ($network === null) {
            throw new RuntimeException("SoChain-Netzwerk für Chain „{$chain->slug}“ nicht konfiguriert.");
        }

        $address = (string) $portfolio->wallet_source;
        $transactions = $this->client->getAddressTransactions($network, $address);

        return $this->mapper->mapSoChainTransactions($transactions, $address, $chain->decimals);
    }
}
