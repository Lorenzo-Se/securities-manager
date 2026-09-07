<?php

use App\Services\Blockchain\TransactionMapper;

it('maps evm explorer deposit for native eth', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapEvmExplorerTransactions(
        [
            [
                'hash' => 'eth-tx-1',
                'timeStamp' => '1700000000',
                'from' => '0x0000000000000000000000000000000000000001',
                'to' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                'value' => '1000000000000000000',
                'blockNumber' => '123',
            ],
        ],
        '0xAbCdEfAbCdEfAbCdEfAbCdEfAbCdEfAbCdEfAbCd',
        18,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[0]->amount)->toBe('1.00000000');
});

it('maps evm explorer withdrawal for native eth', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapEvmExplorerTransactions(
        [
            [
                'hash' => 'eth-tx-2',
                'timeStamp' => '1700000000',
                'from' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                'to' => '0x0000000000000000000000000000000000000001',
                'value' => '500000000000000000',
                'blockNumber' => '124',
            ],
        ],
        '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        18,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('withdrawal')
        ->and($transactions[0]->amount)->toBe('0.50000000');
});

it('maps evm explorer token transfer filtered by contract', function () {
    $mapper = new TransactionMapper;
    $contract = '0xdac17f958d2ee523a2206206994597c13d831ec7';

    $transactions = $mapper->mapEvmExplorerTransactions(
        [
            [
                'hash' => 'usdt-tx-1',
                'timeStamp' => '1700000000',
                'from' => '0x0000000000000000000000000000000000000001',
                'to' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                'value' => '1000000',
                'contractAddress' => $contract,
                'tokenDecimal' => '6',
                'blockNumber' => '125',
            ],
        ],
        '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        18,
        $contract,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[0]->amount)->toBe('1.00000000');
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
        8,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[0]->amount)->toBe('0.50000000');
});

it('maps mempool transactions as withdrawal when address spends funds', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapMempoolTransactions(
        [
            [
                'txid' => 'mempool-tx-withdrawal',
                'status' => ['block_time' => 1700000000],
                'vout' => [
                    ['scriptpubkey_address' => 'bc1qexternal', 'value' => 40000000],
                ],
                'vin' => [
                    [
                        'prevout' => [
                            'scriptpubkey_address' => 'bc1qtest',
                            'value' => 50000000,
                        ],
                    ],
                ],
            ],
        ],
        'bc1qtest',
        8,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('withdrawal')
        ->and($transactions[0]->amount)->toBe('0.50000000');
});

it('maps blockchain info xpub transactions', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapBlockchainInfoXpub([
        'txs' => [
            [
                'hash' => 'btc-xpub-tx',
                'time' => 1700000000,
                'result' => 200000000,
                'tx_index' => 42,
            ],
        ],
    ]);

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->txHash)->toBe('btc-xpub-tx')
        ->and($transactions[0]->amount)->toBe('2.00000000');
});

it('maps xrpl payment deposit', function () {
    $mapper = new TransactionMapper;
    $address = 'rN7n7otQDd6FczFgLdSqtcsAUxDkw6fzRH';

    $transactions = $mapper->mapXrplTransactions(
        [
            [
                'tx' => [
                    'hash' => 'xrp-tx-1',
                    'TransactionType' => 'Payment',
                    'Account' => 'rSender',
                    'Destination' => $address,
                    'Amount' => '1000000',
                    'date' => 1,
                ],
                'meta' => [
                    'delivered_amount' => '1000000',
                ],
            ],
        ],
        $address,
        6,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit');
});

it('maps sochain address transactions', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapSoChainTransactions(
        [
            [
                'txid' => 'doge-tx-1',
                'time' => 1700000000,
                'incoming' => 100000000,
                'outgoing' => 0,
            ],
        ],
        'DAddress',
        8,
    );

    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[0]->amount)->toBe('1.00000000');
});

it('maps hyperliquid fills as deposits and withdrawals', function () {
    $mapper = new TransactionMapper;

    $transactions = $mapper->mapHyperliquidFills([
        [
            'hash' => 'hl-fill-1',
            'tid' => 1,
            'sz' => '10.5',
            'side' => 'B',
            'time' => 1700000000000,
        ],
        [
            'hash' => 'hl-fill-2',
            'tid' => 2,
            'sz' => '3.25',
            'side' => 'A',
            'time' => 1700001000000,
        ],
    ]);

    expect($transactions)->toHaveCount(2)
        ->and($transactions[0]->type)->toBe('deposit')
        ->and($transactions[1]->type)->toBe('withdrawal');
});
