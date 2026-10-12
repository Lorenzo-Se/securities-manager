<?php

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Models\PortfolioShare;
use App\Models\User;
use App\Services\Crypto\CoinGeckoClient;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

function mockValueHistoryUser(User $user): void
{
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
}

function mockMarketChart(array $eurPoints = [], array $usdPoints = []): void
{
    $defaultEurPoints = $eurPoints !== [] ? $eurPoints : [
        ['timestamp' => 1_700_000_000_000, 'price' => 40000.0],
        ['timestamp' => 1_700_432_000_000, 'price' => 50000.0],
    ];

    $defaultUsdPoints = $usdPoints !== [] ? $usdPoints : [
        ['timestamp' => 1_700_000_000_000, 'price' => 44000.0],
        ['timestamp' => 1_700_432_000_000, 'price' => 55000.0],
    ];

    test()->mock(CoinGeckoClient::class, function ($mock) use ($defaultEurPoints, $defaultUsdPoints) {
        $mock->shouldReceive('getMarketChart')
            ->andReturnUsing(function (string $id, int $days, string $currency) use ($defaultEurPoints, $defaultUsdPoints) {
                return $currency === 'usd' ? $defaultUsdPoints : $defaultEurPoints;
            });
    });
}

it('returns value history for portfolio owner with movements', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id', 'currency' => 'eur']);
    mockValueHistoryUser($owner);
    mockMarketChart();

    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '1.00000000',
        'unit_price_usd' => '40000.00000000',
        'occurred_at' => '2023-11-14 12:00:00',
    ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=30")
        ->assertOk()
        ->assertJsonPath('currency', 'eur')
        ->assertJsonPath('days', 30)
        ->assertJsonCount(2, 'points')
        ->assertJsonPath('points.0.value', '40000.00')
        ->assertJsonPath('points.0.profit_amount', '3636.36')
        ->assertJsonPath('points.0.profit_percent', '10.00')
        ->assertJsonPath('points.1.value', '50000.00')
        ->assertJsonPath('points.1.profit_amount', '13636.36')
        ->assertJsonPath('points.1.profit_percent', '37.50');
});

it('returns empty points when portfolio has no movements', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    mockValueHistoryUser($owner);
    mockMarketChart();

    $portfolio = Portfolio::factory()->for($owner)->create();

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=30")
        ->assertOk()
        ->assertJsonPath('points', []);
});

it('returns share-adjusted value history for amount share holder', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    $viewer = User::factory()->create(['keycloak_id' => 'viewer-keycloak-id', 'currency' => 'eur']);
    mockValueHistoryUser($viewer);
    mockMarketChart();

    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '2.00000000',
        'unit_price_usd' => '40000.00000000',
        'occurred_at' => '2023-11-14 12:00:00',
    ]);

    PortfolioShare::factory()->for($portfolio)->create([
        'invited_user_id' => $viewer->id,
        'invited_email' => $viewer->email,
        'share_type' => PortfolioShareType::Amount,
        'share_amount' => '0.50000000',
        'show_total_balance' => false,
        'status' => PortfolioShareStatus::Accepted,
        'accepted_at' => now(),
    ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=30")
        ->assertOk()
        ->assertJsonPath('points.0.value', '20000.00')
        ->assertJsonPath('points.1.value', '25000.00');
});

it('returns 404 when user has no access', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    $stranger = User::factory()->create(['keycloak_id' => 'stranger-keycloak-id']);
    mockValueHistoryUser($stranger);

    $portfolio = Portfolio::factory()->for($owner)->create();

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=30")
        ->assertNotFound();
});

it('returns service unavailable when market data cannot be fetched', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    mockValueHistoryUser($owner);

    test()->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('getMarketChart')
            ->andThrow(new RuntimeException('CoinGecko Rate-Limit erreicht (429).'));
    });

    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '1.00000000',
        'occurred_at' => '2023-11-14 12:00:00',
    ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=365")
        ->assertStatus(503)
        ->assertJsonPath(
            'message',
            'Marktdaten sind derzeit nicht verfügbar. Bitte versuche es später erneut.',
        );
});

it('resolves max days to numeric chart range from first movement', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id', 'currency' => 'eur']);
    mockValueHistoryUser($owner);

    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '1.00000000',
        'unit_price_usd' => '40000.00000000',
        'occurred_at' => now()->subDays(120),
    ]);

    test()->mock(CoinGeckoClient::class, function ($mock) {
        $mock->shouldReceive('getMarketChart')
            ->withArgs(fn (string $id, int $days): bool => $days > 90 && $days <= 122)
            ->twice()
            ->andReturn([
                ['timestamp' => now()->subDays(10)->timestamp * 1000, 'price' => 45000.0],
            ]);
    });

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=max")
        ->assertOk()
        ->assertJsonPath('days', 'max')
        ->assertJsonCount(1, 'points');
});

it('returns null profit when movement unit price is missing', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id', 'currency' => 'eur']);
    mockValueHistoryUser($owner);
    mockMarketChart();

    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '1.00000000',
        'unit_price_usd' => null,
        'occurred_at' => '2023-11-14 12:00:00',
    ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=30")
        ->assertOk()
        ->assertJsonPath('points.0.profit_amount', null)
        ->assertJsonPath('points.0.profit_percent', null);
});

it('validates days parameter', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    mockValueHistoryUser($owner);

    $portfolio = Portfolio::factory()->for($owner)->create();

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?days=14")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days']);
});
