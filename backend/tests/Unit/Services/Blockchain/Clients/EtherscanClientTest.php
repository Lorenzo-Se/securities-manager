<?php

use App\Services\Blockchain\Clients\EtherscanClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('fetches native transactions from etherscan', function () {
    config(['crypto.etherscan.api_key' => 'test-key']);

    Http::fake([
        'api.etherscan.io/*' => Http::response([
            'status' => '1',
            'message' => 'OK',
            'result' => [
                ['hash' => 'tx1', 'timeStamp' => '1', 'from' => '0x1', 'to' => '0x2', 'value' => '1'],
            ],
        ]),
    ]);

    $client = new EtherscanClient(config('crypto.etherscan'));
    $result = $client->getNativeTransactions('0xabcdefabcdefabcdefabcdefabcdefabcdefabcd');

    expect($result)->toHaveCount(1)
        ->and($result[0]['hash'])->toBe('tx1');
});

it('does not cache failed etherscan responses', function () {
    config(['crypto.etherscan.api_key' => 'test-key']);

    Http::fake([
        'api.etherscan.io/*' => Http::sequence()
            ->push(['status' => '0', 'message' => 'NOTOK', 'result' => 'Error'], 200)
            ->push([
                'status' => '1',
                'message' => 'OK',
                'result' => [['hash' => 'tx-ok', 'timeStamp' => '1', 'from' => '0x1', 'to' => '0x2', 'value' => '1']],
            ]),
    ]);

    $client = new EtherscanClient(config('crypto.etherscan'));
    $address = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';
    $cacheKey = 'etherscan.'.md5('txlist'.$address);

    try {
        $client->getNativeTransactions($address);
    } catch (RuntimeException) {
        // expected
    }

    expect(Cache::has($cacheKey))->toBeFalse();

    $result = $client->getNativeTransactions($address);

    expect($result[0]['hash'])->toBe('tx-ok');
});

it('reports etherscan as not configured without api key', function () {
    config(['crypto.etherscan.api_key' => '']);

    $client = new EtherscanClient(config('crypto.etherscan'));

    expect($client->isConfigured())->toBeFalse();
});
