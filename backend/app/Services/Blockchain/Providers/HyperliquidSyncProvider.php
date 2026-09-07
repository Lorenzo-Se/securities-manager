<?php

namespace App\Services\Blockchain\Providers;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\Clients\HyperliquidClient;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;

class HyperliquidSyncProvider implements BlockchainSyncProvider
{
    public function __construct(
        private readonly HyperliquidClient $client,
        private readonly TransactionMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'hyperliquid';
    }

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool
    {
        return $chain->slug === 'hyperliquid' && $walletSourceType === WalletSourceType::Address;
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
        $fills = $this->client->getUserFills($address);

        return $this->mapper->mapHyperliquidFills($fills);
    }
}
