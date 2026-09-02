<?php

use App\Enums\WalletSourceType;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\ChainRegistry;
use App\Services\Crypto\CoinGeckoClient;

it('resolves native bitcoin as utxo xpub coin', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $syncable = $registry->resolve('bitcoin');

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('bitcoin')
        ->and($syncable->walletSourceType)->toBe(WalletSourceType::Xpub)
        ->and($syncable->tokenContract)->toBeNull();
});

it('resolves native ethereum as account address coin', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $syncable = $registry->resolve('ethereum');

    expect($syncable)->not->toBeNull()
        ->and($syncable->chainSlug)->toBe('ethereum')
        ->and($syncable->walletSourceType)->toBe(WalletSourceType::Address);
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

    $syncable = $registry->resolve('usd-coin');

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

it('returns chain config for slug', function () {
    $registry = new ChainRegistry(Mockery::mock(CoinGeckoClient::class));

    $chain = $registry->chainForSlug('bitcoin');

    expect($chain)->toBeInstanceOf(ChainConfig::class)
        ->and($chain->blockchairSlug)->toBe('bitcoin')
        ->and($chain->decimals)->toBe(8);
});
