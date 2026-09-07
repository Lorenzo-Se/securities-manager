<?php

use App\Enums\ChainModel;
use App\Enums\WalletSourceType;
use App\Services\Blockchain\BlockchainSyncProviderRegistry;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\ChainRegistry;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use App\Services\Blockchain\Providers\MempoolSyncProvider;
use App\Services\Crypto\CoinGeckoClient;

it('resolves native bitcoin as utxo xpub coin', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $syncable = $registry->resolve('bitcoin');

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('bitcoin')
        ->and($syncable->walletSourceType)->toBe(WalletSourceType::Xpub)
        ->and($syncable->tokenContract)->toBeNull();
});

it('resolves bitcoin address wallet type', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $syncable = $registry->resolve('bitcoin', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh');

    expect($syncable)->not->toBeNull()
        ->and($syncable->walletSourceType)->toBe(WalletSourceType::Address);
});

it('resolves native ethereum as account address coin', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $syncable = $registry->resolve('ethereum');

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('ethereum')
        ->and($syncable->walletSourceType)->toBe(WalletSourceType::Address);
});

it('resolves usdt on ethereum via 0x address', function () {
    $coinGecko = Mockery::mock(CoinGeckoClient::class);
    $coinGecko->shouldReceive('getCoinDetail')
        ->with('tether')
        ->andReturn([
            'id' => 'tether',
            'platforms' => [
                'ethereum' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
                'tron' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            ],
        ]);

    $registry = new ChainRegistry($coinGecko);

    $syncable = $registry->resolve(
        'tether',
        '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
    );

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('ethereum')
        ->and($syncable->tokenContract)->toBe('0xdac17f958d2ee523a2206206994597c13d831ec7');
});

it('resolves usdt on tron via T address', function () {
    $coinGecko = Mockery::mock(CoinGeckoClient::class);
    $coinGecko->shouldReceive('getCoinDetail')
        ->with('tether')
        ->andReturn([
            'id' => 'tether',
            'platforms' => [
                'ethereum' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
                'tron' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            ],
        ]);

    $registry = new ChainRegistry($coinGecko);

    $syncable = $registry->resolve(
        'tether',
        'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
    );

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('tron');
});

it('resolves token via coingecko platforms', function () {
    $coinGecko = Mockery::mock(CoinGeckoClient::class);
    $coinGecko->shouldReceive('getCoinDetail')
        ->with('usd-coin')
        ->andReturn([
            'id' => 'usd-coin',
            'platforms' => [
                'ethereum' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            ],
        ]);

    $registry = new ChainRegistry($coinGecko);

    $syncable = $registry->resolve(
        'usd-coin',
        '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
    );

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('ethereum')
        ->and($syncable->walletSourceType)->toBe(WalletSourceType::Address)
        ->and($syncable->tokenContract)->toBe('0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48');
});

it('validates xpub format for utxo coins', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    expect($registry->validateWalletSource('bitcoin', 'invalid'))->not->toBeNull();
    expect($registry->validateWalletSource(
        'bitcoin',
        'xpub6CUGRUonZSQ4TWtTMmzYdrLuzeXZfQZGqnpMQigbRs73NNJ6chYAAFGbLH3FLQjpVcTwsydfKpE7LHjPXBj7wTgYrZ1cUxdtJWA',
    ))->toBeNull();
});

it('rejects xpub for litecoin', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $error = $registry->validateWalletSource(
        'litecoin',
        'xpub6CUGRUonZSQ4TWtTMmzYdrLuzeXZfQZGqnpMQigbRs73NNJ6chYAAFGbLH3FLQjpVcTwsydfKpE7LHjPXBj7wTgYrZ1cUxdtJWA',
    );

    expect($error)->toContain('xpub-Sync');
});

it('returns chain config for slug', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $chain = $registry->chainForSlug('bitcoin');

    expect($chain)->toBeInstanceOf(ChainConfig::class)
        ->and($chain->slug)->toBe('bitcoin')
        ->and($chain->decimals)->toBe(8);
});

it('resolves provider from registry', function () {
    $chain = new ChainConfig(
        slug: 'bitcoin',
        provider: 'mempool',
        providers: ['address' => 'mempool'],
        providerOptions: [],
        model: ChainModel::Utxo,
        nativeCoingeckoId: 'bitcoin',
        decimals: 8,
        coingeckoPlatform: '',
    );

    $provider = Mockery::mock(BlockchainSyncProvider::class);
    $provider->shouldReceive('name')->andReturn('mempool');
    $provider->shouldReceive('supports')->andReturn(true);
    $provider->shouldReceive('isConfigured')->andReturn(true);

    $registry = new BlockchainSyncProviderRegistry([$provider]);

    expect($registry->resolve($chain, WalletSourceType::Address))->toBe($provider);
});

it('throws when provider is not configured', function () {
    $chain = new ChainConfig(
        slug: 'ethereum',
        provider: 'etherscan',
        providers: [],
        providerOptions: [],
        model: ChainModel::Account,
        nativeCoingeckoId: 'ethereum',
        decimals: 18,
        coingeckoPlatform: 'ethereum',
    );

    $provider = Mockery::mock(BlockchainSyncProvider::class);
    $provider->shouldReceive('name')->andReturn('etherscan');
    $provider->shouldReceive('supports')->andReturn(true);
    $provider->shouldReceive('isConfigured')->andReturn(false);

    $registry = new BlockchainSyncProviderRegistry([$provider]);

    expect(fn () => $registry->resolve($chain, WalletSourceType::Address))
        ->toThrow(RuntimeException::class, 'nicht konfiguriert');
});

it('resolves mempool provider from container', function () {
    $provider = app(MempoolSyncProvider::class);

    expect($provider->name())->toBe('mempool')
        ->and($provider->isConfigured())->toBeTrue();
});
