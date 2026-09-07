<?php

namespace App\Services\Blockchain\Contracts;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\SyncedTransaction;

interface BlockchainSyncProvider
{
    public function name(): string;

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool;

    public function isConfigured(): bool;

    /**
     * @return list<SyncedTransaction>
     */
    public function fetchTransactions(Portfolio $portfolio, ChainConfig $chain): array;
}
