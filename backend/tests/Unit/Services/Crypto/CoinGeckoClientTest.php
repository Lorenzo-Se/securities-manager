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
