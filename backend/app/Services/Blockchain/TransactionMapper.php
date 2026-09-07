<?php

namespace App\Services\Blockchain;

use DateTimeImmutable;
use DateTimeZone;

class TransactionMapper
{
    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapEvmExplorerTransactions(
        array $transactions,
        string $address,
        int $nativeDecimals = 18,
        ?string $tokenContract = null,
    ): array {
        $synced = [];
        $normalizedAddress = strtolower($address);
        $normalizedContract = $tokenContract !== null ? strtolower($tokenContract) : null;

        foreach ($transactions as $transaction) {
            $txHash = (string) ($transaction['hash'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $amount = $this->resolveEvmAmount($transaction, $normalizedAddress, $nativeDecimals, $normalizedContract);

            if ($amount === null || abs($amount) < 1e-12) {
                continue;
            }

            $timestamp = (int) ($transaction['timeStamp'] ?? 0);
            $occurredAt = $this->timestampToUtc($timestamp);

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: (string) ($transaction['blockNumber'] ?? $txHash),
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapTronGridTransactions(
        array $transactions,
        string $address,
        int $nativeDecimals = 6,
        ?string $tokenContract = null,
    ): array {
        $synced = [];
        $normalizedAddress = $address;
        $isTrc20 = $tokenContract !== null;

        foreach ($transactions as $transaction) {
            if ($isTrc20) {
                $txHash = (string) ($transaction['transaction_id'] ?? '');
                $raw = $transaction['value'] ?? null;
                $decimals = (int) ($transaction['token_info']['decimals'] ?? 6);
                $from = (string) ($transaction['from'] ?? '');
                $to = (string) ($transaction['to'] ?? '');
            } else {
                $rawData = $transaction['raw_data'] ?? null;
                $contract = is_array($rawData) ? ($rawData['contract'][0] ?? null) : null;
                $parameter = is_array($contract) ? ($contract['parameter']['value'] ?? null) : null;

                if (! is_array($parameter)) {
                    continue;
                }

                $txHash = (string) ($transaction['txID'] ?? '');
                $raw = $parameter['amount'] ?? null;
                $decimals = $nativeDecimals;
                $from = $this->hexToTronAddress((string) ($parameter['owner_address'] ?? ''));
                $to = $this->hexToTronAddress((string) ($parameter['to_address'] ?? ''));
            }

            if ($txHash === '' || $raw === null || ! is_numeric((string) $raw)) {
                continue;
            }

            $value = (float) $raw / (10 ** $decimals);

            if ($value < 1e-12) {
                continue;
            }

            $amount = match (true) {
                $to === $normalizedAddress => $value,
                $from === $normalizedAddress => -$value,
                default => null,
            };

            if ($amount === null || abs($amount) < 1e-12) {
                continue;
            }

            $timestamp = (int) ($transaction['block_timestamp'] ?? 0);
            $occurredAt = $timestamp > 0
                ? (new DateTimeImmutable('@'.(int) ($timestamp / 1000)))->setTimezone(new DateTimeZone('UTC'))
                : null;

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: $txHash,
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapHeliusTransactions(
        array $transactions,
        string $address,
        int $nativeDecimals = 9,
        ?string $tokenContract = null,
    ): array {
        $synced = [];

        foreach ($transactions as $transaction) {
            $txHash = (string) ($transaction['signature'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $amount = $this->resolveHeliusAmount($transaction, $address, $nativeDecimals, $tokenContract);

            if ($amount === null || abs($amount) < 1e-12) {
                continue;
            }

            $timestamp = (int) ($transaction['timestamp'] ?? 0);
            $occurredAt = $this->timestampToUtc($timestamp);

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: $txHash,
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapBlockfrostTransactions(
        array $transactions,
        string $address,
    ): array {
        $synced = [];

        foreach ($transactions as $transaction) {
            $txHash = (string) ($transaction['tx_hash'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $amount = $this->resolveBlockfrostAmount($transaction, $address);

            if ($amount === null || abs($amount) < 1e-12) {
                continue;
            }

            $blockTime = (int) ($transaction['block_time'] ?? 0);
            $occurredAt = $this->timestampToUtc($blockTime);

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: $txHash,
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapSoChainTransactions(
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

            $incoming = (float) ($transaction['incoming'] ?? 0);
            $outgoing = (float) ($transaction['outgoing'] ?? 0);
            $change = ($incoming - $outgoing) / (10 ** $decimals);

            if (abs($change) < 1e-12) {
                continue;
            }

            $timestamp = (int) ($transaction['time'] ?? 0);
            $occurredAt = $this->timestampToUtc($timestamp);

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $change > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($change), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: $txHash,
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     * @return list<SyncedTransaction>
     */
    public function mapXrplTransactions(
        array $transactions,
        string $address,
        int $decimals = 6,
    ): array {
        $synced = [];

        foreach ($transactions as $entry) {
            $tx = $entry['tx'] ?? null;

            if (! is_array($tx)) {
                continue;
            }

            if (($tx['TransactionType'] ?? '') !== 'Payment') {
                continue;
            }

            $txHash = (string) ($tx['hash'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $destination = (string) ($tx['Destination'] ?? '');
            $account = (string) ($tx['Account'] ?? '');
            $delivered = $entry['meta']['delivered_amount'] ?? ($tx['Amount'] ?? null);

            if ($delivered === null || ! is_numeric((string) $delivered)) {
                continue;
            }

            $value = (float) $delivered / (10 ** $decimals);

            $amount = match (true) {
                $destination === $address => $value,
                $account === $address => -$value,
                default => null,
            };

            if ($amount === null || abs($amount) < 1e-12) {
                continue;
            }

            $date = (int) ($tx['date'] ?? 0);
            $occurredAt = $date > 0
                ? (new DateTimeImmutable('@'.($date + 946684800)))->setTimezone(new DateTimeZone('UTC'))
                : null;

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $amount > 0 ? 'deposit' : 'withdrawal',
                amount: number_format(abs($amount), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: $txHash,
            );
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $fills
     * @return list<SyncedTransaction>
     */
    public function mapHyperliquidFills(array $fills): array
    {
        $synced = [];

        foreach ($fills as $fill) {
            $txHash = (string) ($fill['hash'] ?? $fill['tid'] ?? '');

            if ($txHash === '') {
                continue;
            }

            $size = (float) ($fill['sz'] ?? 0);
            $side = (string) ($fill['side'] ?? '');

            if ($size < 1e-12) {
                continue;
            }

            $type = $side === 'B' ? 'deposit' : 'withdrawal';
            $timestamp = (int) ($fill['time'] ?? 0);
            $occurredAt = $timestamp > 0
                ? (new DateTimeImmutable('@'.(int) ($timestamp / 1000)))->setTimezone(new DateTimeZone('UTC'))
                : null;

            if ($occurredAt === null) {
                continue;
            }

            $synced[] = new SyncedTransaction(
                txHash: $txHash,
                type: $type,
                amount: number_format(abs($size), 8, '.', ''),
                occurredAt: $occurredAt,
                externalId: (string) ($fill['tid'] ?? $txHash),
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
            $occurredAt = $this->timestampToUtc($timestamp);

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
            $occurredAt = $this->timestampToUtc($timestamp);

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
     * @param  array<string, mixed>  $transaction
     */
    private function resolveEvmAmount(
        array $transaction,
        string $normalizedAddress,
        int $nativeDecimals,
        ?string $normalizedContract,
    ): ?float {
        if ($normalizedContract !== null) {
            $contract = strtolower((string) ($transaction['contractAddress'] ?? ''));
            $to = strtolower((string) ($transaction['to'] ?? ''));
            $from = strtolower((string) ($transaction['from'] ?? ''));
            $value = (float) ($transaction['value'] ?? 0);
            $decimals = (int) ($transaction['tokenDecimal'] ?? 18);

            if ($contract !== $normalizedContract) {
                return null;
            }

            if ($to === $normalizedAddress) {
                return $value / (10 ** $decimals);
            }

            if ($from === $normalizedAddress) {
                return -($value / (10 ** $decimals));
            }

            return null;
        }

        $to = strtolower((string) ($transaction['to'] ?? ''));
        $from = strtolower((string) ($transaction['from'] ?? ''));
        $value = (float) ($transaction['value'] ?? 0);

        if ($to === $normalizedAddress) {
            return $value / (10 ** $nativeDecimals);
        }

        if ($from === $normalizedAddress) {
            return -($value / (10 ** $nativeDecimals));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function resolveHeliusAmount(
        array $transaction,
        string $address,
        int $nativeDecimals,
        ?string $tokenContract,
    ): ?float {
        if ($tokenContract !== null) {
            $total = 0.0;

            foreach ($transaction['tokenTransfers'] ?? [] as $transfer) {
                if (! is_array($transfer)) {
                    continue;
                }

                $mint = (string) ($transfer['mint'] ?? '');

                if ($mint !== $tokenContract) {
                    continue;
                }

                $value = (float) ($transfer['tokenAmount'] ?? 0);
                $to = (string) ($transfer['toUserAccount'] ?? '');
                $from = (string) ($transfer['fromUserAccount'] ?? '');

                if ($to === $address) {
                    $total += $value;
                } elseif ($from === $address) {
                    $total -= $value;
                }
            }

            return $total === 0.0 ? null : $total;
        }

        $total = 0.0;

        foreach ($transaction['nativeTransfers'] ?? [] as $transfer) {
            if (! is_array($transfer)) {
                continue;
            }

            $value = (float) ($transfer['amount'] ?? 0) / (10 ** $nativeDecimals);
            $to = (string) ($transfer['toUserAccount'] ?? '');
            $from = (string) ($transfer['fromUserAccount'] ?? '');

            if ($to === $address) {
                $total += $value;
            } elseif ($from === $address) {
                $total -= $value;
            }
        }

        return $total === 0.0 ? null : $total;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function resolveBlockfrostAmount(array $transaction, string $address): ?float
    {
        $inputs = $transaction['inputs'] ?? [];
        $outputs = $transaction['outputs'] ?? [];
        $received = 0.0;
        $sent = 0.0;

        foreach ($outputs as $output) {
            if (! is_array($output)) {
                continue;
            }

            if (($output['address'] ?? '') === $address) {
                $amounts = $output['amount'] ?? [];

                foreach ($amounts as $amount) {
                    if (is_array($amount) && ($amount['unit'] ?? '') === 'lovelace') {
                        $received += (float) ($amount['quantity'] ?? 0);
                    }
                }
            }
        }

        foreach ($inputs as $input) {
            if (! is_array($input)) {
                continue;
            }

            if (($input['address'] ?? '') === $address) {
                $amounts = $input['amount'] ?? [];

                foreach ($amounts as $amount) {
                    if (is_array($amount) && ($amount['unit'] ?? '') === 'lovelace') {
                        $sent += (float) ($amount['quantity'] ?? 0);
                    }
                }
            }
        }

        $change = ($received - $sent) / 1_000_000;

        return $change === 0.0 ? null : $change;
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

    private function timestampToUtc(int $timestamp): ?DateTimeImmutable
    {
        if ($timestamp <= 0) {
            return null;
        }

        return (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    private function hexToTronAddress(string $hexAddress): string
    {
        if ($hexAddress === '') {
            return '';
        }

        if (str_starts_with($hexAddress, 'T')) {
            return $hexAddress;
        }

        return $hexAddress;
    }
}
