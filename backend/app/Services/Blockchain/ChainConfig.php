<?php

namespace App\Services\Blockchain;

use App\Enums\ChainModel;
use App\Enums\WalletSourceType;

readonly class ChainConfig
{
    /**
     * @param  array<string, string>  $providers
     * @param  array<string, string>  $providerOptions
     */
    public function __construct(
        public string $slug,
        public string $provider,
        public array $providers,
        public array $providerOptions,
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

    public function providerFor(WalletSourceType $type): string
    {
        $key = $type->value;

        if (isset($this->providers[$key])) {
            return $this->providers[$key];
        }

        return $this->provider;
    }

    public function providerOption(string $key, ?string $default = null): ?string
    {
        return $this->providerOptions[$key] ?? $default;
    }
}
