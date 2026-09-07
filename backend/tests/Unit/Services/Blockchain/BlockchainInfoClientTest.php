<?php

use App\Services\Blockchain\BlockchainInfoClient;
use Illuminate\Support\Facades\Http;

it('fetches xpub dashboard without chain suffix', function () {
    Http::fake([
        'blockchain.info/*' => Http::response([
            'txs' => [
                [
                    'hash' => 'btc-tx-1',
                    'time' => 1700000000,
                    'result' => 100000000,
                    'tx_index' => 1,
                ],
            ],
        ]),
    ]);

    $client = new BlockchainInfoClient;
    $dashboard = $client->getXpubDashboard('xpub6CmZamQcHw2TPtbGmJNEvRgfhLwitarvzFn3fBYEEkFTqztus7W7CNbf48Kxuj1bRRBmZPzQocB6qar9ay6buVkQk73ftKE1z4tt9cPHWRn');

    expect($dashboard['txs'])->toHaveCount(1)
        ->and($dashboard['txs'][0]['hash'])->toBe('btc-tx-1');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'blockchain.info/multiaddr')
            && ($request['active'] ?? '') === 'xpub6CmZamQcHw2TPtbGmJNEvRgfhLwitarvzFn3fBYEEkFTqztus7W7CNbf48Kxuj1bRRBmZPzQocB6qar9ay6buVkQk73ftKE1z4tt9cPHWRn'
            && ($request['format'] ?? '') === 'json'
            && ! str_contains((string) ($request['active'] ?? ''), '|');
    });
});

it('reports invalid address errors from blockchain.info', function () {
    Http::fake([
        'blockchain.info/*' => Http::response([
            'error' => 'user-error',
            'message' => 'invalid address',
        ], 400),
    ]);

    $client = new BlockchainInfoClient;

    expect(fn () => $client->getXpubDashboard('xpub6invalidkeydata000000000000000000000000000000000000000000000000000000000'))
        ->toThrow(RuntimeException::class, 'Bitcoin-xpub konnte nicht abgefragt werden');
});
