<?php

use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Models\User;
use App\Services\Portfolio\PortfolioCostBasisCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates cost basis using average cost method', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $calculator = new PortfolioCostBasisCalculator;

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'unit_price_usd' => '10000',
        'occurred_at' => '2026-08-10 10:00:00',
    ]);
    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'unit_price_usd' => '20000',
        'occurred_at' => '2026-08-11 10:00:00',
    ]);
    PortfolioMovement::factory()->for($portfolio)->withdrawal()->create([
        'amount' => '0.5',
        'unit_price_usd' => '18000',
        'occurred_at' => '2026-08-12 10:00:00',
    ]);

    $movements = $portfolio->movements()->get();

    expect($calculator->calculate($movements))->toBe('22500.00000000');
});

it('returns null when a movement is missing unit price', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $calculator = new PortfolioCostBasisCalculator;

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'unit_price_usd' => null,
        'occurred_at' => '2026-08-10 10:00:00',
    ]);

    expect($calculator->calculate($portfolio->movements()->get()))->toBeNull();
});

it('returns zero cost basis for empty movements', function () {
    $calculator = new PortfolioCostBasisCalculator;

    expect($calculator->calculate(collect()))->toBe('0.00000000');
});

it('calculates cost basis only from movements before a timestamp', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $calculator = new PortfolioCostBasisCalculator;

    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'unit_price_usd' => '10000',
        'occurred_at' => '2026-08-10 10:00:00',
    ]);
    PortfolioMovement::factory()->for($portfolio)->deposit()->create([
        'amount' => '1',
        'unit_price_usd' => '20000',
        'occurred_at' => '2026-08-11 10:00:00',
    ]);

    $movements = $portfolio->movements()->get();
    $timestamp = strtotime('2026-08-10 12:00:00');

    expect($calculator->costBasisAtTimestamp($movements, $timestamp))->toBe('10000.00000000');
});
