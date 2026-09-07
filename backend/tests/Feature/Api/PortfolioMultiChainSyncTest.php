<?php

use App\Enums\SyncStatus;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Services\Blockchain\PortfolioSyncService;
use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function mockMultiChainCoinGecko(): void
{
    test()->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('getHistoricalPrice')->andReturn(100.0);
    });
}

it('syncs ethereum native transactions via etherscan', function () {
    config(['crypto.etherscan.api_key' => 'test-etherscan-key']);

    Http::fake([
        'api.etherscan.io/*' => Http::response([
            'status' => '1',
            'message' => 'OK',
            'result' => [
                [
                    'hash' => 'eth-sync-tx',
                    'timeStamp' => '1700000000',
                    'from' => '0x0000000000000000000000000000000000000001',
                    'to' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                    'value' => '1000000000000000000',
                    'blockNumber' => '100',
                ],
            ],
        ]),
    ]);

    mockMultiChainCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'ethereum',
        'wallet_source_type' => 'address',
        'wallet_source' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        'chain_slug' => 'ethereum',
        'sync_status' => SyncStatus::Pending,
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    $portfolio->refresh();

    expect($portfolio->sync_status)->toBe(SyncStatus::Synced);

    $movement = PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('deposit')
        ->and($movement->tx_hash)->toBe('eth-sync-tx');
});

it('syncs xrp transactions via xrpl', function () {
    Http::fake([
        'xrplcluster.com/*' => Http::response([
            'result' => [
                'transactions' => [
                    [
                        'tx' => [
                            'hash' => 'xrp-sync-tx',
                            'TransactionType' => 'Payment',
                            'Account' => 'rSender',
                            'Destination' => 'rN7n7otQDd6FczFgLdSqtcsAUxDkw6fzRH',
                            'Amount' => '2000000',
                            'date' => 1,
                        ],
                        'meta' => [
                            'delivered_amount' => '2000000',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    mockMultiChainCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'ripple',
        'wallet_source_type' => 'address',
        'wallet_source' => 'rN7n7otQDd6FczFgLdSqtcsAUxDkw6fzRH',
        'chain_slug' => 'ripple',
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    expect($portfolio->fresh()->sync_status)->toBe(SyncStatus::Synced)
        ->and(PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->count())->toBe(1);
});

it('syncs dogecoin address via sochain', function () {
    Http::fake([
        'sochain.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'txs' => [
                    [
                        'txid' => 'doge-sync-tx',
                        'time' => 1700000000,
                        'incoming' => 50000000,
                        'outgoing' => 0,
                    ],
                ],
            ],
        ]),
    ]);

    mockMultiChainCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'dogecoin',
        'wallet_source_type' => 'address',
        'wallet_source' => 'D8KkXqGcqGqGcqGcqGcqGcqGcqGcqGcqGcq',
        'chain_slug' => 'dogecoin',
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    expect($portfolio->fresh()->sync_status)->toBe(SyncStatus::Synced);
});

it('fails sync when etherscan api key is missing', function () {
    config(['crypto.etherscan.api_key' => '']);

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'ethereum',
        'wallet_source_type' => 'address',
        'wallet_source' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        'chain_slug' => 'ethereum',
    ]);

    try {
        app(PortfolioSyncService::class)->sync($portfolio->fresh());
    } catch (RuntimeException) {
        // expected
    }

    $portfolio->refresh();

    expect($portfolio->sync_status)->toBe(SyncStatus::Failed)
        ->and($portfolio->sync_error)->toContain('nicht konfiguriert');
});

it('syncs hyperliquid fills', function () {
    Http::fake([
        'api.hyperliquid.xyz/*' => Http::response([
            [
                'hash' => 'hl-sync-fill',
                'tid' => 99,
                'sz' => '5',
                'side' => 'B',
                'time' => 1700000000000,
            ],
        ]),
    ]);

    mockMultiChainCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'hyperliquid',
        'wallet_source_type' => 'address',
        'wallet_source' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        'chain_slug' => 'hyperliquid',
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    expect($portfolio->fresh()->sync_status)->toBe(SyncStatus::Synced);
});
