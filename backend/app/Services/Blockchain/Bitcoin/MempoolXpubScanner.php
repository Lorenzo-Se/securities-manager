<?php

namespace App\Services\Blockchain\Bitcoin;

use App\Services\Blockchain\MempoolClient;
use App\Services\Blockchain\SyncedTransaction;
use App\Services\Blockchain\TransactionMapper;
use Illuminate\Support\Facades\Log;

class MempoolXpubScanner
{
    public function __construct(
        private readonly Bip32PublicKeyReader $reader,
        private readonly BitcoinAddressEncoder $encoder,
        private readonly MempoolClient $mempool,
        private readonly TransactionMapper $mapper,
    ) {}

    /**
     * @return list<SyncedTransaction>
     */
    public function scan(string $extendedKey, int $decimals = 8): array
    {
        $parsed = $this->reader->parse($extendedKey);
        $gapLimit = (int) config('crypto.bitcoin_hd.gap_limit', 20);
        $maxScanIndex = (int) config('crypto.bitcoin_hd.max_scan_index', 100);

        /** @var array<string, SyncedTransaction> $transactionsByHash */
        $transactionsByHash = [];

        foreach ([0, 1] as $chain) {
            $gap = 0;

            for ($index = 0; $index < $maxScanIndex && $gap < $gapLimit; $index++) {
                $child = $this->reader->deriveChild($parsed->key, $chain, $index);
                $address = $this->encoder->encode($child, $parsed->scheme, $this->reader);

                if (! $this->mempool->addressHasActivity($address)) {
                    $gap++;

                    continue;
                }

                $gap = 0;

                Log::info('Bitcoin HD address scan hit', [
                    'chain' => $chain,
                    'index' => $index,
                    'scheme' => $parsed->scheme->value,
                ]);

                $rawTransactions = $this->mempool->getCachedAddressTransactions($address);
                $mapped = $this->mapper->mapMempoolTransactions($rawTransactions, $address, $decimals);

                foreach ($mapped as $transaction) {
                    $this->mergeTransaction($transactionsByHash, $transaction);
                }
            }
        }

        $transactions = array_values(array_filter(
            $transactionsByHash,
            static fn (SyncedTransaction $transaction): bool => (float) $transaction->amount >= 1e-12,
        ));

        usort($transactions, fn (SyncedTransaction $a, SyncedTransaction $b): int => $a->occurredAt <=> $b->occurredAt);

        return $transactions;
    }

    /**
     * @param  array<string, SyncedTransaction>  $transactionsByHash
     */
    private function mergeTransaction(array &$transactionsByHash, SyncedTransaction $transaction): void
    {
        $signedAmount = (float) $transaction->amount * ($transaction->type === 'deposit' ? 1 : -1);

        if (isset($transactionsByHash[$transaction->txHash])) {
            $existing = $transactionsByHash[$transaction->txHash];
            $signedAmount += (float) $existing->amount * ($existing->type === 'deposit' ? 1 : -1);
        }

        if (abs($signedAmount) < 1e-12) {
            unset($transactionsByHash[$transaction->txHash]);

            return;
        }

        $transactionsByHash[$transaction->txHash] = new SyncedTransaction(
            txHash: $transaction->txHash,
            type: $signedAmount > 0 ? 'deposit' : 'withdrawal',
            amount: number_format(abs($signedAmount), 8, '.', ''),
            occurredAt: $transaction->occurredAt,
            externalId: $transaction->externalId,
        );
    }
}
