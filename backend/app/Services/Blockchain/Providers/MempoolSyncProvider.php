<?php

namespace App\Services\Blockchain\Providers;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\MempoolClient;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;

class MempoolSyncProvider implements BlockchainSyncProvider
{
    public function __construct(
        private readonly MempoolClient $client,
        private readonly TransactionMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'mempool';
    }

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool
    {
        return $chain->slug === 'bitcoin' && $walletSourceType === WalletSourceType::Address;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return list<SyncedTransaction>
     */
    public function fetchTransactions(Portfolio $portfolio, ChainConfig $chain): array
    {
        $address = (string) $portfolio->wallet_source;
        $transactions = $this->client->getAddressTransactions($address);

        return $this->mapper->mapMempoolTransactions($transactions, $address, $chain->decimals);
    }
}
