<?php

namespace App\Services\Blockchain\Providers;

use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\Clients\TronGridClient;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;

class TronGridSyncProvider implements BlockchainSyncProvider
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly TransactionMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'trongrid';
    }

    public function supports(ChainConfig $chain, WalletSourceType $walletSourceType): bool
    {
        return $chain->slug === 'tron' && $walletSourceType === WalletSourceType::Address;
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
            ? $this->client->getTrc20Transactions($address, $portfolio->token_contract)
            : $this->client->getNativeTransactions($address);

        return $this->mapper->mapTronGridTransactions(
            $transactions,
            $address,
            $chain->decimals,
            $portfolio->token_contract,
        );
    }
}
