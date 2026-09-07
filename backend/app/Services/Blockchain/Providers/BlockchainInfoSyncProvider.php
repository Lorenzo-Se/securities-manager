<?php

namespace App\Services\Blockchain\Providers;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\Bitcoin\MempoolXpubScanner;
use App\Services\Blockchain\BlockchainInfoClient;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;

class BlockchainInfoSyncProvider implements BlockchainSyncProvider
{
    public function __construct(
        private readonly BlockchainInfoClient $client,
        private readonly MempoolXpubScanner $mempoolScanner,
        private readonly TransactionMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'blockchain_info';
    }

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool
    {
        return $chain->slug === 'bitcoin' && $walletSourceType === WalletSourceType::Xpub;
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
        $xpub = (string) $portfolio->wallet_source;

        if (preg_match('/^(zpub|ypub|vpub|upub)/', $xpub)) {
            return $this->mempoolScanner->scan($xpub, $chain->decimals);
        }

        $dashboard = $this->client->getXpubDashboard($xpub);

        return $this->mapper->mapBlockchainInfoXpub($dashboard, $chain->decimals);
    }
}
