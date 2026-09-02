<?php

namespace App\Services\Blockchain;

use App\Enums\WalletSourceType;
use DateTimeImmutable;
use DateTimeZone;

class TransactionMapper
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @return list<SyncedTransaction>
     */
    public function mapBlockchairDashboard(
        array $dashboard,
        ChainConfig $chain,
        WalletSourceType $sourceType,
        ?string $tokenContract = null,
    ): array {
        /** @var array<string, array<string, mixed>> $transactions */
        $transactions = $dashboard['data']['transactions'] ?? [];

        $synced = [];

        foreach ($transactions as $hash => $transactionData) {
            $transaction = is_array($transactionData['transaction'] ?? null)
                ? $transactionData['transaction']
                : null;

            if ($transaction === null) {
                continue;
            }

            $txHash = (string) ($transaction['hash'] ?? $hash);
            $amount = $this->resolveAmount($transactionData, $chain, $tokenContract);

            if ($amount === null || abs($amount) < 1e-12) {
                continue;
            }

            $occurredAt = $this->parseTime((string) ($transaction['time'] ?? ''));

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: (string) ($transaction['id'] ?? $txHash),
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapMempoolTransactions(
        array $transactions,
        string $address,
        int $decimals = 8,
    ): array {
        $synced = [];

        foreach ($transactions as $transaction) {
            $txHash = (string) ($transaction['txid'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $balanceChange = $this->calculateMempoolBalanceChange($transaction, $address, $decimals);

            if ($balanceChange === null || abs($balanceChange) < 1e-12) {
                continue;
            }

            $timestamp = (int) ($transaction['status']['block_time'] ?? 0);
            $occurredAt = $timestamp > 0
                ? (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('UTC'))
                : null;

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $balanceChange > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($balanceChange), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: $txHash,
            );
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return list<SyncedTransaction>
     */
    public function mapBlockchainInfoXpub(array $dashboard, int $decimals = 8): array
    {
        /** @var list<array<string, mixed>> $transactions */
        $transactions = $dashboard['txs'] ?? [];
        $synced = [];

        foreach ($transactions as $transaction) {
            if (! isset($transaction['result']) || ! is_numeric($transaction['result'])) {
                continue;
            }

            $amount = ((float) $transaction['result']) / (10 ** $decimals);

            if (abs($amount) < 1e-12) {
                continue;
            }

            $txHash = (string) ($transaction['hash'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $timestamp = (int) ($transaction['time'] ?? 0);
            $occurredAt = $timestamp > 0
                ? (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('UTC'))
                : null;

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: (string) ($transaction['tx_index'] ?? $txHash),
            );
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $transactionData
     */
    private function resolveAmount(
        array $transactionData,
        ChainConfig $chain,
        ?string $tokenContract,
    ): ?float {
        if ($tokenContract !== null) {
            return $this->resolveTokenAmount($transactionData, $chain, $tokenContract);
        }

        $transaction = $transactionData['transaction'] ?? null;

        if (! is_array($transaction) || ! isset($transaction['balance_change'])) {
            return null;
        }

        return ((float) $transaction['balance_change']) / (10 ** $chain->decimals);
    }

    /**
     * @param  array<string, mixed>  $transactionData
     */
    private function resolveTokenAmount(
        array $transactionData,
        ChainConfig $chain,
        string $tokenContract,
    ): ?float {
        $calls = $transactionData['calls'] ?? [];

        if (! is_array($calls)) {
            return null;
        }

        $total = 0.0;
        $normalizedContract = strtolower($tokenContract);

        foreach ($calls as $call) {
            if (! is_array($call)) {
                continue;
            }

            $callContract = strtolower((string) ($call['token_address'] ?? $call['recipient'] ?? ''));

            if ($callContract !== $normalizedContract) {
                continue;
            }

            $value = (float) ($call['value'] ?? 0);

            if ($value === 0.0) {
                continue;
            }

            $sender = strtolower((string) ($call['sender'] ?? ''));
            $recipient = strtolower((string) ($call['recipient'] ?? ''));

            if ($recipient !== '' && $sender !== '') {
                $total += $value;
            }
        }

        if ($total === 0.0) {
            return null;
        }

        return $total / (10 ** $chain->decimals);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function calculateMempoolBalanceChange(
        array $transaction,
        string $address,
        int $decimals,
    ): ?float {
        $received = 0.0;
        $sent = 0.0;

        foreach ($transaction['vout'] ?? [] as $output) {
            if (! is_array($output)) {
                continue;
            }

            if (($output['scriptpubkey_address'] ?? null) === $address) {
                $received += (float) ($output['value'] ?? 0);
            }
        }

        foreach ($transaction['vin'] ?? [] as $input) {
            if (! is_array($input)) {
                continue;
            }

            $prevout = $input['prevout'] ?? null;

            if (is_array($prevout) && ($prevout['scriptpubkey_address'] ?? null) === $address) {
                $sent += (float) ($prevout['value'] ?? 0);
            }
        }

        $change = ($received - $sent) / (10 ** $decimals);

        return $change === 0.0 ? null : $change;
    }

    private function parseTime(string $time): ?DateTimeImmutable
    {
        if ($time === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $time, new DateTimeZone('UTC'));

        return $parsed ?: null;
    }
}
