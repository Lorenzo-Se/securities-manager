<?php

namespace App\Services\Blockchain;

use App\Enums\SyncStatus;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortfolioSyncService
{
    public function __construct(
        private readonly BlockchainSyncProviderRegistry $providerRegistry,
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

        $walletSourceType = $portfolio->wallet_source_type ?? $chain->walletSourceType();

        $portfolio->update([
            'sync_status' => SyncStatus::Syncing,
            'sync_error' => null,
        ]);

        try {
            $provider = $this->providerRegistry->resolve($chain, $walletSourceType);
            $transactions = $provider->fetchTransactions($portfolio, $chain);

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
                'provider' => $provider->name(),
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

    private function upsertMovement(Portfolio $portfolio, SyncedTransaction $transaction): void
    {
        $existing = PortfolioMovement::query()
            ->where('portfolio_id', $portfolio->id)
            ->where('tx_hash', $transaction->txHash)
            ->first();

        $unitPriceUsd = null;

        try {
            $unitPriceUsd = $this->coinGecko->getHistoricalPrice(
                $portfolio->crypto_id,
                $transaction->occurredAt,
                'usd',
            );
        } catch (Throwable $exception) {
            Log::warning('Could not resolve unit price for movement', [
                'portfolio_id' => $portfolio->id,
                'crypto_id' => $portfolio->crypto_id,
                'tx_hash' => $transaction->txHash,
                'error' => $exception->getMessage(),
            ]);
        }

        $attributes = [
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'occurred_at' => $transaction->occurredAt,
            'external_id' => $transaction->externalId,
        ];

        if ($unitPriceUsd !== null) {
            $attributes['unit_price_usd'] = number_format($unitPriceUsd, 8, '.', '');
        }

        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        $portfolio->movements()->create([
            ...$attributes,
            'tx_hash' => $transaction->txHash,
            'unit_price_usd' => $attributes['unit_price_usd'] ?? null,
        ]);
    }
}
