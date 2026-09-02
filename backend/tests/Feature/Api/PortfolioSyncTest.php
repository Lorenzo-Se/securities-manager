<?php

use App\Enums\SyncStatus;
use App\Jobs\SyncPortfolioJob;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Models\User;
use App\Services\Blockchain\PortfolioSyncService;
use App\Services\Crypto\CoinGeckoClient;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function mockSyncAuthenticatedUser(?User $user = null): User
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

function mockSyncCoinGecko(): void
{
    test()->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('findCoinById')
            ->with('bitcoin')
            ->andReturn([
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'image' => 'https://example.com/btc.png',
                'market_cap_rank' => 1,
            ]);

        $mock->shouldReceive('getHistoricalPrice')
            ->andReturn(50000.0);
    });
}

const VALID_XPUB = 'xpub6CUGRUonZSQ4TWtTMmzYdrLuzeXZfQZGqnpMQigbRs73NNJ6chYAAFGbLH3FLQjpVcTwsydfKpE7LHjPXBj7wTgYrZ1cUxdtJWA';

it('syncs portfolio movements from blockchain.info for bitcoin xpub', function () {
    Http::fake([
        'blockchain.info/*' => Http::response([
            'txs' => [
                [
                    'hash' => 'txhash1',
                    'time' => strtotime('2026-01-10 10:00:00'),
                    'result' => 200000000,
                    'tx_index' => 99,
                ],
            ],
        ]),
    ]);

    mockSyncCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'bitcoin',
        'wallet_source_type' => 'xpub',
        'wallet_source' => VALID_XPUB,
        'chain_slug' => 'bitcoin',
        'sync_status' => SyncStatus::Pending,
    ]);

    app(PortfolioSyncService::class)->sync($portfolio->fresh());

    $portfolio->refresh();

    expect($portfolio->sync_status)->toBe(SyncStatus::Synced)
        ->and($portfolio->last_synced_at)->not->toBeNull();

    $movement = PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('deposit')
        ->and($movement->amount)->toBe('2.00000000')
        ->and($movement->tx_hash)->toBe('txhash1');
});

it('does not duplicate movements on repeated sync', function () {
    Http::fake([
        'blockchain.info/*' => Http::response([
            'txs' => [
                [
                    'hash' => 'txhash1',
                    'time' => strtotime('2026-01-10 10:00:00'),
                    'result' => 100000000,
                    'tx_index' => 1,
                ],
            ],
        ]),
    ]);

    mockSyncCoinGecko();

    $portfolio = Portfolio::factory()->create([
        'crypto_id' => 'bitcoin',
        'wallet_source_type' => 'xpub',
        'wallet_source' => VALID_XPUB,
        'chain_slug' => 'bitcoin',
    ]);

    $service = app(PortfolioSyncService::class);
    $service->sync($portfolio->fresh());
    $service->sync($portfolio->fresh());

    expect(PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->count())->toBe(1);
});

it('dispatches sync job when creating portfolio via api', function () {
    Bus::fake();
    mockSyncCoinGecko();
    mockSyncAuthenticatedUser();

    $this->withToken('valid-token')
        ->postJson('/api/portfolios', [
            'name' => 'Mein BTC',
            'crypto_id' => 'bitcoin',
            'wallet_source' => VALID_XPUB,
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'pending');

    Bus::assertDispatched(SyncPortfolioJob::class);
});

it('triggers sync when updating wallet source', function () {
    Bus::fake();
    mockSyncCoinGecko();
    $user = mockSyncAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create([
        'wallet_source' => VALID_XPUB,
        'sync_status' => SyncStatus::Synced,
    ]);

    $newXpub = 'xpub6CUGRUonZSQ4TWtTMmzYdrLuzeXZfQZGqnpMQigbRs73NNJ6chYAAFGbLH3FLQjpVcTwsydfKpE7LHjPXBj7wTgYrZ1cUxdtJWB';

    $this->withToken('valid-token')
        ->patchJson("/api/portfolios/{$portfolio->id}", [
            'wallet_source' => $newXpub,
        ])
        ->assertOk()
        ->assertJsonPath('sync_status', 'pending');

    Bus::assertDispatched(SyncPortfolioJob::class);
});

it('starts manual sync via api endpoint', function () {
    Bus::fake();
    $user = mockSyncAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create([
        'sync_status' => SyncStatus::Synced,
    ]);

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/sync")
        ->assertStatus(202)
        ->assertJsonPath('sync_status', 'pending');

    Bus::assertDispatched(SyncPortfolioJob::class);
});
