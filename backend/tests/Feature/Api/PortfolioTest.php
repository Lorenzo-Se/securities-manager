<?php

use App\Models\Portfolio;
use App\Models\User;
use App\Services\Crypto\CoinGeckoClient;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mockAuthenticatedUser(?User $user = null): User
{
    $user ??= User::factory()->create([
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

    test()->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')
            ->andReturn($claims);
    });

    test()->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')
            ->with($claims)
            ->andReturn($user);
    });

    return $user;
}

function mockCoinGeckoCoins(): void
{
    test()->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('getCoinsList')
            ->andReturn([
                ['id' => 'bitcoin', 'symbol' => 'btc', 'name' => 'Bitcoin'],
                ['id' => 'ethereum', 'symbol' => 'eth', 'name' => 'Ethereum'],
            ]);

        $mock->shouldReceive('findCoinById')
            ->with('bitcoin')
            ->andReturn([
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'image' => 'https://example.com/btc.png',
                'market_cap_rank' => 1,
            ]);

        $mock->shouldReceive('findCoinById')
            ->with('invalid-coin')
            ->andReturn(null);

        $mock->shouldReceive('searchCoins')
            ->andReturnUsing(function (?string $search) {
                $coins = [
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
                ];

                if ($search === null || trim($search) === '') {
                    return $coins;
                }

                return array_values(array_filter(
                    $coins,
                    fn (array $coin) => str_contains(strtolower($coin['name']), strtolower($search))
                        || str_contains(strtolower($coin['symbol']), strtolower($search)),
                ));
            });
    });
}

it('returns unauthorized without token for portfolios', function () {
    $this->getJson('/api/portfolios')
        ->assertUnauthorized();
});

it('lists portfolios for authenticated user', function () {
    mockCoinGeckoCoins();
    $user = mockAuthenticatedUser();
    $otherUser = User::factory()->create();

    Portfolio::factory()->for($user)->create(['name' => 'Mein BTC']);
    Portfolio::factory()->for($otherUser)->create(['name' => 'Fremdes Portfolio']);

    $this->withToken('valid-token')
        ->getJson('/api/portfolios')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => 'Mein BTC']);
});

it('creates a portfolio with valid crypto', function () {
    mockCoinGeckoCoins();
    mockAuthenticatedUser();

    $this->withToken('valid-token')
        ->postJson('/api/portfolios', [
            'name' => 'Mein BTC',
            'crypto_id' => 'bitcoin',
        ])
        ->assertCreated()
        ->assertJson([
            'name' => 'Mein BTC',
            'crypto_id' => 'bitcoin',
            'crypto_symbol' => 'btc',
            'crypto_name' => 'Bitcoin',
        ]);

    $this->assertDatabaseHas('portfolios', [
        'name' => 'Mein BTC',
        'crypto_id' => 'bitcoin',
    ]);
});

it('rejects portfolio creation with invalid crypto_id', function () {
    mockCoinGeckoCoins();
    mockAuthenticatedUser();

    $this->withToken('valid-token')
        ->postJson('/api/portfolios', [
            'name' => 'Invalid',
            'crypto_id' => 'invalid-coin',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crypto_id']);
});

it('deletes own portfolio', function () {
    mockCoinGeckoCoins();
    $user = mockAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('portfolios', ['id' => $portfolio->id]);
});

it('returns not found when deleting another users portfolio', function () {
    mockCoinGeckoCoins();
    mockAuthenticatedUser();
    $otherUser = User::factory()->create();
    $portfolio = Portfolio::factory()->for($otherUser)->create();

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}")
        ->assertNotFound();
});
