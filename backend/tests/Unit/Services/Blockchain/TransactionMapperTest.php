<?php

use App\Enums\ChainModel;
use App\Enums\WalletSourceType;
use App\Services\Blockchain\ChainConfig;
use App\Services\Blockchain\TransactionMapper;

it('maps blockchair deposit from positive balance change', function () {
    $mapper = new TransactionMapper;
    $chain = new ChainConfig('bitcoin', ChainModel::Utxo, 'bitcoin', 8, '');

    $dashboard = [
        'data' => [
            'transactions' => [
                'abc123' => [
                    'transaction' => [
                        'hash' => 'abc123',
                        'time' => '2026-01-15 12:00:00',
                        'balance_change' => 100000000,
                        'id' => 1,
                    ],
                ],
            ],
        ],
    ];

    $transactions = $mapper->mapBlockchairDashboard(
        $dashboard,
        $chain,
        WalletSourceType::Xpub,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[0]->amount)->toBe('1.00000000')
        ->and($transactions[0]->txHash)->toBe('abc123');
});

it('maps blockchair withdrawal from negative balance change', function () {
    $mapper = new TransactionMapper;
    $chain = new ChainConfig('ethereum', ChainModel::Account, 'ethereum', 18, 'ethereum');

    $dashboard = [
        'data' => [
            'transactions' => [
                'def456' => [
                    'transaction' => [
                        'hash' => 'def456',
                        'time' => '2026-02-20 08:30:00',
                        'balance_change' => -500000000000000000,
                        'id' => 2,
                    ],
                ],
            ],
        ],
    ];

    $transactions = $mapper->mapBlockchairDashboard(
        $dashboard,
        $chain,
        WalletSourceType::Address,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('withdrawal')
        ->and($transactions[0]->amount)->toBe('0.50000000');
});

it('maps mempool transactions for bitcoin address', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapMempoolTransactions(
        [
            [
                'txid' => 'mempool-tx-1',
                'status' => ['block_time' => 1700000000],
                'vout' => [
                    ['scriptpubkey_address' => 'bc1qtest', 'value' => 50000000],
                ],
                'vin' => [],
            ],
        ],
        'bc1qtest',
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[0]->amount)->toBe('0.50000000')
        ->and($transactions[0]->txHash)->toBe('mempool-tx-1');
});

it('maps blockchain.info xpub transactions', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapBlockchainInfoXpub([
        'txs' => [
            [
                'hash' => 'abc123',
                'time' => 1700000000,
                'result' => -50000000,
                'tx_index' => 5,
            ],
        ],
    ]);

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('withdrawal')
        ->and($transactions[0]->amount)->toBe('0.50000000');
});
