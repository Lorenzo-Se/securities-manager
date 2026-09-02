<?php

namespace App\Services\Blockchain;

use App\Enums\ChainModel;
use App\Enums\WalletSourceType;

readonly class ChainConfig
{
    public function __construct(
        public string $blockchairSlug,
        public ChainModel $model,
        public string $nativeCoingeckoId,
        public int $decimals,
        public string $coingeckoPlatform,
    ) {}

    public function walletSourceType(): WalletSourceType
    {
        return $this->model === ChainModel::Utxo
            ? WalletSourceType::Xpub
            : WalletSourceType::Address;
    }
}
