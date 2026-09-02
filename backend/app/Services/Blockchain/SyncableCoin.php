<?php

namespace App\Services\Blockchain;

use App\Enums\WalletSourceType;

readonly class SyncableCoin
{
    public function __construct(
        public string $cryptoId,
        public string $chainSlug,
        public WalletSourceType $walletSourceType,
        public ?string $tokenContract,
    ) {}
}
