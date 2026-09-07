<?php

namespace App\Services\Blockchain\Providers;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\Clients\BscScanClient;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;

class BscScanSyncProvider implements BlockchainSyncProvider
{
    public function __construct(
        private readonly BscScanClient $client,
        private readonly TransactionMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'bscscan';
    }

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool
    {
        return $chain->slug === 'binance-smart-chain' && $walletSourceType === WalletSourceType::Address;
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
        $address = (string) $portfolio->wallet_source;

        $transactions = $portfolio->token_contract !== null
            ? $this->client->getTokenTransactions($address, $portfolio->token_contract)
            : $this->client->getNativeTransactions($address);

        return $this->mapper->mapEvmExplorerTransactions(
            $transactions,
            $address,
            $chain->decimals,
            $portfolio->token_contract,
        );
    }
}
