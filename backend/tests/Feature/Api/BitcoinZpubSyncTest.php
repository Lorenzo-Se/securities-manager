<?php

use App\Enums\SyncStatus;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Services\Blockchain\Bitcoin\Bip32PublicKeyReader;
use App\Services\Blockchain\Bitcoin\BitcoinAddressEncoder;
use App\Services\Blockchain\PortfolioSyncService;
use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const BIP84_ZPUB = 'zpub6rFR7y4Q2AijBEqTUquhVz398htDFrtymD9xYYfG1m4wAcvPhXNfE3EfH1r1ADqtfSdVCToUG868RvUUkgDKf31mGDtKsAYz2oz2AGutZYs';
const BIP84_FIRST_ADDRESS = 'bc1qcr8te4kr609gcawutmrza0j4xv80jy8z306fyu';

function mockZpubSyncCoinGecko(): void
{
    test()->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('getHistoricalPrice')->andReturn(50000.0);
    });
}

it('derives bip84 first native segwit address from zpub', function () {
    $reader = new Bip32PublicKeyReader;
    $encoder = new BitcoinAddressEncoder;

    $parsed = $reader->parse(BIP84_ZPUB);
    $child = $reader->deriveChild($parsed->key, 0, 0);
    $address = $encoder->encode($child, $parsed->scheme, $reader);

    expect($address)->toBe(BIP84_FIRST_ADDRESS);
});

it('scans zpub transactions via mempool address derivation', function () {
    Http::fake([
        'mempool.space/api/address/'.BIP84_FIRST_ADDRESS => Http::response([
            'chain_stats' => ['tx_count' => 1],
            'mempool_stats' => ['tx_count' => 0],
        ]),
        'mempool.space/api/address/'.BIP84_FIRST_ADDRESS.'/txs' => Http::response([
            [
                'txid' => 'zpub-derived-tx',
                'status' => ['block_time' => 1700000000],
                'vout' => [
                    ['scriptpubkey_address' => BIP84_FIRST_ADDRESS, 'value' => 100000000],
                ],
                'vin' => [],
            ],
        ]),
        'mempool.space/api/address/*' => Http::response([
            'chain_stats' => ['tx_count' => 0],
            'mempool_stats' => ['tx_count' => 0],
        ]),
    ]);

    mockZpubSyncCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'bitcoin',
        'wallet_source_type' => 'xpub',
        'wallet_source' => BIP84_ZPUB,
        'chain_slug' => 'bitcoin',
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    $portfolio->refresh();

    expect($portfolio->sync_status)->toBe(SyncStatus::Synced);

    $movement = PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->tx_hash)->toBe('zpub-derived-tx');
});

it('maps zpub withdrawals with change address as net withdrawal', function () {
    $reader = new Bip32PublicKeyReader;
    $encoder = new BitcoinAddressEncoder;
    $parsed = $reader->parse(BIP84_ZPUB);
    $changeAddress = $encoder->encode(
        $reader->deriveChild($parsed->key, 1, 0),
        $parsed->scheme,
        $reader,
    );

    Http::fake([
        'mempool.space/api/address/'.BIP84_FIRST_ADDRESS => Http::response([
            'chain_stats' => ['tx_count' => 1],
            'mempool_stats' => ['tx_count' => 0],
        ]),
        'mempool.space/api/address/'.BIP84_FIRST_ADDRESS.'/txs' => Http::response([
            [
                'txid' => 'zpub-withdrawal-tx',
                'status' => ['block_time' => 1700000000],
                'vout' => [
                    ['scriptpubkey_address' => 'bc1qexternalrecipient', 'value' => 70000000],
                    ['scriptpubkey_address' => $changeAddress, 'value' => 29000000],
                ],
                'vin' => [
                    [
                        'prevout' => [
                            'scriptpubkey_address' => BIP84_FIRST_ADDRESS,
                            'value' => 100000000,
                        ],
                    ],
                ],
            ],
        ]),
        'mempool.space/api/address/'.$changeAddress => Http::response([
            'chain_stats' => ['tx_count' => 1],
            'mempool_stats' => ['tx_count' => 0],
        ]),
        'mempool.space/api/address/'.$changeAddress.'/txs' => Http::response([
            [
                'txid' => 'zpub-withdrawal-tx',
                'status' => ['block_time' => 1700000000],
                'vout' => [
                    ['scriptpubkey_address' => 'bc1qexternalrecipient', 'value' => 70000000],
                    ['scriptpubkey_address' => $changeAddress, 'value' => 29000000],
                ],
                'vin' => [
                    [
                        'prevout' => [
                            'scriptpubkey_address' => BIP84_FIRST_ADDRESS,
                            'value' => 100000000,
                        ],
                    ],
                ],
            ],
        ]),
        'mempool.space/api/address/*' => Http::response([
            'chain_stats' => ['tx_count' => 0],
            'mempool_stats' => ['tx_count' => 0],
        ]),
    ]);

    mockZpubSyncCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'bitcoin',
        'wallet_source_type' => 'xpub',
        'wallet_source' => BIP84_ZPUB,
        'chain_slug' => 'bitcoin',
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    $movement = PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('withdrawal')
        ->and($movement->amount)->toBe('0.71000000')
        ->and($movement->tx_hash)->toBe('zpub-withdrawal-tx');
});
