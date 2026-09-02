<?php

namespace App\Services\Blockchain;

use App\Enums\SyncStatus;
use App\Enums\WalletSourceType;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PortfolioSyncService
{
    public function __construct(
        private readonly BlockchairClient $blockchair,
        private readonly MempoolClient $mempool,
        private readonly BlockchainInfoClient $blockchainInfo,
        private readonly TransactionMapper $mapper,
        private readonly ChainRegistry $chainRegistry,
        private readonly CoinGeckoClient $coinGecko,
    ) {}

    public function sync(Portfolio $portfolio): void
    {
        if ($portfolio->wallet_source === null || $portfolio->chain_slug === null) {
            $portfolio->update([
                'sync_status' => SyncStatus::Failed,
                'sync_error' => 'Wallet-Quelle erforderlich.',
            ]);

            return;
        }

        $chain = $this->chainRegistry->chainForSlug($portfolio->chain_slug);

        if ($chain === null) {
            $portfolio->update([
                'sync_status' => SyncStatus::Failed,
                'sync_error' => 'Chain-Konfiguration nicht gefunden.',
            ]);

            return;
        }

        $portfolio->update([
            'sync_status' => SyncStatus::Syncing,
            'sync_error' => null,
        ]);

        try {
            $transactions = $this->fetchTransactions($portfolio, $chain);

            foreach ($transactions as $transaction) {
                $this->upsertMovement($portfolio, $transaction);
            }

            $portfolio->update([
                'sync_status' => SyncStatus::Synced,
                'sync_error' => null,
                'last_synced_at' => now(),
            ]);

            Log::info('Portfolio sync completed', [
                'portfolio_id' => $portfolio->id,
                'chain_slug' => $portfolio->chain_slug,
                'transaction_count' => count($transactions),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Portfolio sync failed', [
                'portfolio_id' => $portfolio->id,
                'chain_slug' => $portfolio->chain_slug,
                'wallet_source_type' => $portfolio->wallet_source_type?->value,
                'error' => $exception->getMessage(),
            ]);

            $portfolio->update([
                'sync_status' => SyncStatus::Failed,
                'sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return list<SyncedTransaction>
     */
    private function fetchTransactions(Portfolio $portfolio, ChainConfig $chain): array
    {
        $walletSource = $portfolio->wallet_source;

        if ($walletSource === null) {
            return [];
        }

        if ($chain->blockchairSlug === 'bitcoin') {
            return $this->fetchBitcoinTransactions($portfolio, $chain, $walletSource);
        }

        return $this->fetchBlockchairTransactions($portfolio, $chain, $walletSource);
    }

    /**
     * @return list<SyncedTransaction>
     */
    private function fetchBitcoinTransactions(
        Portfolio $portfolio,
        ChainConfig $chain,
        string $walletSource,
    ): array {
        if ($portfolio->wallet_source_type === WalletSourceType::Xpub) {
            return $this->fetchBitcoinXpubTransactions($walletSource, $chain);
        }

        try {
            $transactions = $this->mempool->getAddressTransactions($walletSource);

            return $this->mapper->mapMempoolTransactions(
                $transactions,
                $walletSource,
                $chain->decimals,
            );
        } catch (RuntimeException $exception) {
            Log::info('Mempool address sync failed, trying Blockchair', [
                'error' => $exception->getMessage(),
            ]);

            return $this->fetchBlockchairTransactions($portfolio, $chain, $walletSource);
        }
    }

    /**
     * @return list<SyncedTransaction>
     */
    private function fetchBitcoinXpubTransactions(string $xpub, ChainConfig $chain): array
    {
        try {
            $dashboard = $this->blockchainInfo->getXpubDashboard($xpub);

            Log::info('Bitcoin xpub sync via blockchain.info', [
                'transaction_count' => count($dashboard['txs'] ?? []),
            ]);

            return $this->mapper->mapBlockchainInfoXpub($dashboard, $chain->decimals);
        } catch (RuntimeException $exception) {
            Log::info('Blockchain.info xpub sync failed, trying Blockchair', [
                'error' => $exception->getMessage(),
            ]);
        }

        if (! $this->blockchair->isConfigured()) {
            throw new RuntimeException(
                'Bitcoin-xpub-Sync fehlgeschlagen. Blockchair ist ohne API-Schlüssel nicht verfügbar.',
            );
        }

        $dashboard = $this->blockchair->getXpubDashboard($chain->blockchairSlug, $xpub);

        return $this->mapper->mapBlockchairDashboard(
            $dashboard,
            $chain,
            WalletSourceType::Xpub,
        );
    }

    /**
     * @return list<SyncedTransaction>
     */
    private function fetchBlockchairTransactions(
        Portfolio $portfolio,
        ChainConfig $chain,
        string $walletSource,
    ): array {
        if (! $this->blockchair->isConfigured()) {
            throw new RuntimeException(
                'Blockchair API-Schlüssel fehlt. Setze BLOCKCHAIR_API_KEY in der backend/.env.',
            );
        }

        if ($portfolio->wallet_source_type === WalletSourceType::Xpub) {
            $dashboard = $this->blockchair->getXpubDashboard($chain->blockchairSlug, $walletSource);

            return $this->mapper->mapBlockchairDashboard(
                $dashboard,
                $chain,
                WalletSourceType::Xpub,
                $portfolio->token_contract,
            );
        }

        $dashboard = $this->blockchair->getAddressDashboard($chain->blockchairSlug, $walletSource);

        return $this->mapper->mapBlockchairDashboard(
            $dashboard,
            $chain,
            WalletSourceType::Address,
            $portfolio->token_contract,
        );
    }

    private function upsertMovement(Portfolio $portfolio, SyncedTransaction $transaction): void
    {
        $existing = PortfolioMovement::query()
            ->where('portfolio_id', $portfolio->id)
            ->where('tx_hash', $transaction->txHash)
            ->first();

        if ($existing !== null) {
            return;
        }

        $unitPriceUsd = $this->coinGecko->getHistoricalPrice(
            $portfolio->crypto_id,
            $transaction->occurredAt,
            'usd',
        );

        $portfolio->movements()->create([
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'unit_price_usd' => number_format($unitPriceUsd, 8, '.', ''),
            'occurred_at' => $transaction->occurredAt,
            'tx_hash' => $transaction->txHash,
            'external_id' => $transaction->externalId,
        ]);
    }
}
