<?php

use App\Services\Blockchain\MempoolClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('skips transaction fetch for inactive addresses', function () {
    Http::fake([
        'mempool.space/api/address/bc1qinactive' => Http::response([
            'chain_stats' => ['tx_count' => 0],
            'mempool_stats' => ['tx_count' => 0],
        ]),
        'mempool.space/api/address/bc1qinactive/txs' => Http::response([], 500),
    ]);

    $client = new MempoolClient;

    expect($client->collectAddressTransactions('bc1qinactive'))->toBe([]);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/address/bc1qinactive'));
});

it('retries mempool requests on rate limit', function () {
    Http::fake([
        'mempool.space/api/address/bc1qactive' => Http::sequence()
            ->push(['status' => 'rate limited'], 429)
            ->push([
                'chain_stats' => ['tx_count' => 1],
                'mempool_stats' => ['tx_count' => 0],
            ]),
        'mempool.space/api/address/bc1qactive/txs' => Http::response([
            [
                'txid' => 'retry-tx',
                'status' => ['block_time' => 1700000000],
                'vout' => [
                    ['scriptpubkey_address' => 'bc1qactive', 'value' => 100000000],
                ],
                'vin' => [],
            ],
        ]),
    ]);

    config([
        'crypto.mempool.request_delay_ms' => 0,
        'crypto.mempool.retry_backoff_ms' => 0,
        'crypto.mempool.max_retries' => 2,
    ]);

    $client = new MempoolClient;

    expect($client->collectAddressTransactions('bc1qactive'))->toHaveCount(1)
        ->and($client->collectAddressTransactions('bc1qactive')[0]['txid'])->toBe('retry-tx');

    Http::assertSentCount(3);
});

it('serves stale mempool metadata when rate limited', function () {
    $cacheKey = 'mempool.address.meta.'.md5('bc1qcached');
    Cache::put($cacheKey.'.stale', [
        'chain_stats' => ['tx_count' => 1],
        'mempool_stats' => ['tx_count' => 0],
    ], 3600);

    Http::fake([
        'mempool.space/api/*' => Http::response(['status' => 'rate limited'], 429),
    ]);

    config([
        'crypto.mempool.request_delay_ms' => 0,
        'crypto.mempool.retry_backoff_ms' => 0,
        'crypto.mempool.max_retries' => 1,
    ]);

    $client = new MempoolClient;

    expect($client->addressHasActivity('bc1qcached'))->toBeTrue();
});

it('caches inactive addresses longer than active ones', function () {
    Http::fake([
        'mempool.space/api/address/bc1qempty' => Http::response([
            'chain_stats' => ['tx_count' => 0],
            'mempool_stats' => ['tx_count' => 0],
        ]),
    ]);

    config([
        'crypto.mempool.request_delay_ms' => 0,
        'crypto.mempool.inactive_address_cache_ttl' => 7200,
    ]);

    $client = new MempoolClient;
    $client->addressHasActivity('bc1qempty');

    $cacheKey = 'mempool.address.meta.'.md5('bc1qempty');

    expect(Cache::get($cacheKey))->toBeArray()
        ->and(Cache::get($cacheKey.'.stale'))->toBeArray();
});
