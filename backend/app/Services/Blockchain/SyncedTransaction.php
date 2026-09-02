<?php

namespace App\Services\Blockchain;

use DateTimeImmutable;

readonly class SyncedTransaction
{
    public function __construct(
        public string $txHash,
        public string $type,
        public string $amount,
        public DateTimeImmutable $occurredAt,
        public ?string $externalId = null,
    ) {}
}
