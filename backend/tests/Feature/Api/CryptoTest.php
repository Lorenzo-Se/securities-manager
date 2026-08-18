<?php

use App\Models\User;
use App\Services\Crypto\CoinGeckoClient;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class)->in(__FILE__);

it('returns unauthorized without token for crypto coins', function () {
    $this->getJson('/api/crypto/coins')
        ->assertUnauthorized();
});

it('returns searchable crypto coins', function () {
    $user = User::factory()->create([
        'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    ]);

    $claims = [
        'sub' => $user->keycloak_id,
        'email' => $user->email,
        'name' => $user->name,
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')->andReturn($claims);
    });

    $this->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')->with($claims)->andReturn($user);
    });

    $this->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('searchCoins')
            ->with('btc')
            ->andReturn([
                [
                    'id' => 'bitcoin',
                    'symbol' => 'btc',
                    'name' => 'Bitcoin',
                    'image' => 'https://example.com/btc.png',
                    'market_cap_rank' => 1,
                ],
            ]);
    });

    $this->withToken('valid-token')
        ->getJson('/api/crypto/coins?search=btc')
        ->assertOk()
        ->assertJson([
            [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'image' => 'https://example.com/btc.png',
                'market_cap_rank' => 1,
            ],
        ]);
});

it('returns crypto prices for given ids', function () {
    $user = User::factory()->create([
        'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    ]);

    $claims = [
        'sub' => $user->keycloak_id,
        'email' => $user->email,
        'name' => $user->name,
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')->andReturn($claims);
    });

    $this->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')->with($claims)->andReturn($user);
    });

    $this->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('getPrices')
            ->with(['bitcoin'])
            ->andReturn(['bitcoin' => ['eur' => 45000.12]]);
    });

    $this->withToken('valid-token')
        ->getJson('/api/crypto/prices?ids=bitcoin')
        ->assertOk()
        ->assertJson(['bitcoin' => ['eur' => 45000.12]]);
});

it('validates crypto prices ids parameter', function () {
    $user = User::factory()->create([
        'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    ]);

    $claims = [
        'sub' => $user->keycloak_id,
        'email' => $user->email,
        'name' => $user->name,
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')->andReturn($claims);
    });

    $this->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')->with($claims)->andReturn($user);
    });

    $this->withToken('valid-token')
        ->getJson('/api/crypto/prices')
        ->assertUnprocessable();
});

it('searches coins by name or symbol', function () {
    Illuminate\Support\Facades\Cache::flush();

    Http::fake([
        'api.coingecko.com/api/v3/search*' => Http::response([
            'coins' => [
                [
                    'id' => 'ethereum',
                    'name' => 'Ethereum',
                    'symbol' => 'ETH',
                    'market_cap_rank' => 2,
                    'thumb' => 'https://example.com/eth.png',
                ],
                [
                    'id' => 'unrelated',
                    'name' => 'Unrelated',
                    'symbol' => 'XYZ',
                    'market_cap_rank' => 500,
                    'thumb' => 'https://example.com/xyz.png',
                ],
            ],
        ]),
    ]);

    $client = app(CoinGeckoClient::class);
    $coins = $client->searchCoins('eth');

    expect($coins)->toHaveCount(1)
        ->and($coins[0]['id'])->toBe('ethereum')
        ->and($coins[0]['image'])->toBe('https://example.com/eth.png');
});

it('sorts search results by market cap rank', function () {
    Illuminate\Support\Facades\Cache::flush();

    Http::fake([
        'api.coingecko.com/api/v3/search*' => Http::response([
            'coins' => [
                [
                    'id' => 'litecoin',
                    'name' => 'Litecoin',
                    'symbol' => 'LTC',
                    'market_cap_rank' => 20,
                    'thumb' => 'https://example.com/ltc.png',
                ],
                [
                    'id' => 'bitcoin',
                    'name' => 'Bitcoin',
                    'symbol' => 'BTC',
                    'market_cap_rank' => 1,
                    'thumb' => 'https://example.com/btc.png',
                ],
            ],
        ]),
    ]);

    $client = app(CoinGeckoClient::class);
    $coins = $client->searchCoins('coin');

    expect($coins)->toHaveCount(2)
        ->and($coins[0]['id'])->toBe('bitcoin')
        ->and($coins[1]['id'])->toBe('litecoin');
});

it('returns top coins sorted by market cap when no search is provided', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/markets*' => Http::response([
            [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'image' => 'https://example.com/btc.png',
                'market_cap_rank' => 1,
            ],
            [
                'id' => 'ethereum',
                'symbol' => 'eth',
                'name' => 'Ethereum',
                'image' => 'https://example.com/eth.png',
                'market_cap_rank' => 2,
            ],
        ]),
    ]);

    $client = app(CoinGeckoClient::class);
    $coins = $client->searchCoins(null);

    expect($coins[0]['id'])->toBe('bitcoin')
        ->and($coins[0]['image'])->toBe('https://example.com/btc.png');
});

it('fetches prices from coingecko api', function () {
    Http::fake([
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'bitcoin' => ['eur' => 45000.12],
        ]),
    ]);

    $client = app(CoinGeckoClient::class);
    $prices = $client->getPrices(['bitcoin']);

    expect($prices)->toBe(['bitcoin' => ['eur' => 45000.12]]);
});
