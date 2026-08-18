<?php

use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Models\User;
use App\Services\Crypto\CoinGeckoClient;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mockMovementAuthenticatedUser(?User $user = null): User
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

function mockMovementCoinGecko(float $unitPriceUsd = 45000.12): void
{
    test()->mock(CoinGeckoClient::class, function ($mock) use ($unitPriceUsd) {
        $mock->shouldReceive('getHistoricalPrice')
            ->andReturn($unitPriceUsd);
    });
}

it('returns portfolio with balance', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1.5',
        'unit_price_usd' => '40000',
        'occurred_at' => '2026-08-10 10:00:00',
    ]);
    PortfolioMovement::factory()->for($portfolio)->withdrawal()->create([
        'amount' => '0.25',
        'unit_price_usd' => '42000',
        'occurred_at' => '2026-08-12 15:30:00',
    ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}")
        ->assertOk()
        ->assertJson([
            'id' => $portfolio->id,
            'balance' => '1.25000000',
            'cost_basis_usd' => '50000.00000000',
        ]);
});

it('returns not found when showing another users portfolio', function () {
    mockMovementAuthenticatedUser();
    $otherUser = User::factory()->create();
    $portfolio = Portfolio::factory()->for($otherUser)->create();

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}")
        ->assertNotFound();
});

it('lists movements for own portfolio', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'occurred_at' => '2026-08-10 09:00:00',
    ]);
    PortfolioMovement::factory()->for($portfolio)->withdrawal()->create([
        'amount' => '0.5',
        'occurred_at' => '2026-08-11 18:00:00',
    ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/movements")
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.type', 'withdrawal')
        ->assertJsonPath('1.type', 'deposit');
});

it('returns not found when listing movements for another users portfolio', function () {
    mockMovementAuthenticatedUser();
    $otherUser = User::factory()->create();
    $portfolio = Portfolio::factory()->for($otherUser)->create();

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/movements")
        ->assertNotFound();
});

it('creates a deposit movement', function () {
    mockMovementCoinGecko(50000.5);
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/movements", [
            'type' => 'deposit',
            'amount' => '0.5',
            'occurred_at' => '2026-08-15T14:30:00',
        ])
        ->assertCreated()
        ->assertJson([
            'type' => 'deposit',
            'amount' => '0.50000000',
            'unit_price_usd' => '50000.50000000',
        ])
        ->assertJsonPath('occurred_at', fn ($value) => str_starts_with((string) $value, '2026-08-15T14:30:00'));

    expect(PortfolioMovement::query()->where('portfolio_id', $portfolio->id)->count())->toBe(1);
});

it('creates a withdrawal when balance is sufficient', function () {
    mockMovementCoinGecko();
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '2',
        'unit_price_usd' => '40000',
        'occurred_at' => '2026-08-10 10:00:00',
    ]);

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/movements", [
            'type' => 'withdrawal',
            'amount' => '1.5',
            'occurred_at' => '2026-08-15T16:00:00',
        ])
        ->assertCreated()
        ->assertJson([
            'type' => 'withdrawal',
            'amount' => '1.50000000',
        ]);
});

it('rejects withdrawal exceeding balance at occurred_at', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'occurred_at' => '2026-08-10 10:00:00',
    ]);

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/movements", [
            'type' => 'withdrawal',
            'amount' => '1.5',
            'occurred_at' => '2026-08-15T16:00:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('allows withdrawal at past date based on balance at occurred_at', function () {
    mockMovementCoinGecko();
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '2',
        'unit_price_usd' => '40000',
        'occurred_at' => '2026-08-10 10:00:00',
    ]);
    PortfolioMovement::factory()->for($portfolio)->withdrawal()->create([
        'amount' => '2',
        'unit_price_usd' => '45000',
        'occurred_at' => '2026-08-20 10:00:00',
    ]);

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/movements", [
            'type' => 'withdrawal',
            'amount' => '1',
            'occurred_at' => '2026-08-15T16:00:00',
        ])
        ->assertCreated()
        ->assertJson([
            'type' => 'withdrawal',
            'amount' => '1.00000000',
        ]);
});

it('rejects invalid amount', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/movements", [
            'type' => 'deposit',
            'amount' => '0',
            'occurred_at' => '2026-08-15T14:30:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('rejects future timestamp', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/movements", [
            'type' => 'deposit',
            'amount' => '0.5',
            'occurred_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occurred_at']);
});

it('deletes own movement', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();
    $movement = PortfolioMovement::factory()->for($portfolio)->deposit()->create();

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}/movements/{$movement->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('portfolio_movements', ['id' => $movement->id]);
});

it('returns not found when deleting movement from another users portfolio', function () {
    mockMovementAuthenticatedUser();
    $otherUser = User::factory()->create();
    $portfolio = Portfolio::factory()->for($otherUser)->create();
    $movement = PortfolioMovement::factory()->for($portfolio)->deposit()->create();

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}/movements/{$movement->id}")
        ->assertNotFound();
});

it('returns not found when deleting movement from wrong portfolio', function () {
    $user = mockMovementAuthenticatedUser();
    $portfolio = Portfolio::factory()->for($user)->create();
    $otherPortfolio = Portfolio::factory()->for($user)->create();
    $movement = PortfolioMovement::factory()->for($otherPortfolio)->deposit()->create();

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}/movements/{$movement->id}")
        ->assertNotFound();
});
