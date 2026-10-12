<?php

use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('uses spot price for today instead of history endpoint', function () {
    Http::fake([
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'bitcoin' => ['usd' => 95000.0],
        ]),
        'api.coingecko.com/api/v3/coins/*/history*' => Http::response([], 500),
    ]);

    $client = new CoinGeckoClient;
    $price = $client->getHistoricalPrice('bitcoin', new DateTimeImmutable('today', new DateTimeZone('UTC')));

    expect($price)->toBe(95000.0);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/simple/price'));
});

it('fetches historical price for past dates', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/*/history*' => Http::response([
            'market_data' => [
                'current_price' => ['usd' => 42000.0],
            ],
        ]),
    ]);

    $client = new CoinGeckoClient;
    $price = $client->getHistoricalPrice('bitcoin', new DateTimeImmutable('2026-01-10'));

    expect($price)->toBe(42000.0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/history'));
});

it('falls back to spot price when historical request fails', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/*/history*' => Http::response([
            'status' => ['error_code' => 429, 'error_message' => 'Rate limit'],
        ], 429),
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'tether' => ['usd' => 1.0],
        ]),
    ]);

    $client = new CoinGeckoClient;
    $price = $client->getHistoricalPrice('tether', new DateTimeImmutable('2026-01-10'));

    expect($price)->toBe(1.0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/history'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/simple/price'));
});

it('does not cache failed historical lookups', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/*/history*' => Http::sequence()
            ->push(['status' => ['error_code' => 429]], 429)
            ->push([
                'market_data' => [
                    'current_price' => ['usd' => 50000.0],
                ],
            ]),
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'bitcoin' => ['usd' => 90000.0],
        ]),
    ]);

    $client = new CoinGeckoClient;
    $date = new DateTimeImmutable('2026-01-10');

    expect($client->getHistoricalPrice('bitcoin', $date))->toBe(90000.0)
        ->and($client->getHistoricalPrice('bitcoin', $date))->toBe(50000.0);
});

it('fetches market chart prices', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/*/market_chart*' => Http::response([
            'prices' => [
                [1_700_000_000_000, 42000.0],
                [1_700_086_400_000, 43000.0],
            ],
        ]),
    ]);

    $client = new CoinGeckoClient;
    $chart = $client->getMarketChart('bitcoin', 30, 'eur');

    expect($chart)->toBe([
        ['timestamp' => 1_700_000_000_000, 'price' => 42000.0],
        ['timestamp' => 1_700_086_400_000, 'price' => 43000.0],
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/market_chart')
        && $request['vs_currency'] === 'eur'
        && $request['days'] === '30'
        && ! isset($request['interval']));
});

it('requests daily interval for long market chart ranges', function () {
    Http::fake([
        'api.coingecko.com/api/v3/coins/*/market_chart*' => Http::response([
            'prices' => [
                [1_700_000_000_000, 42000.0],
            ],
        ]),
    ]);

    $client = new CoinGeckoClient;
    $client->getMarketChart('bitcoin', 365, 'usd');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/market_chart')
        && $request['days'] === '365'
        && $request['interval'] === 'daily');
});

it('serves stale market chart when coingecko is rate limited', function () {
    $client = new CoinGeckoClient;
    $cacheKey = 'coingecko.market_chart.'.md5('bitcoin.30.eur');
    $staleKey = $cacheKey.'.stale';
    $staleChart = [
        ['timestamp' => 1_700_000_000_000, 'price' => 42000.0],
    ];

    Cache::put($staleKey, $staleChart, 3600);

    Http::fake([
        'api.coingecko.com/api/v3/coins/*/market_chart*' => Http::response([
            'status' => ['error_code' => 429],
        ], 429),
    ]);

    $chart = $client->getMarketChart('bitcoin', 30, 'eur');

    expect($chart)->toBe($staleChart);
});

it('serves stale prices when coingecko is rate limited', function () {
    $client = new CoinGeckoClient;
    $cacheKey = 'coingecko.prices.'.md5('bitcoin.usd');
    $staleKey = $cacheKey.'.stale';

    Cache::put($staleKey, ['bitcoin' => ['usd' => 44000.0]], 3600);

    Http::fake([
        'api.coingecko.com/api/v3/simple/price*' => Http::response([
            'status' => ['error_code' => 429],
        ], 429),
    ]);

    $prices = $client->getPrices(['bitcoin'], 'usd');

    expect($prices)->toBe(['bitcoin' => ['usd' => 44000.0]]);
});
