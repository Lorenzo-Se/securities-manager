<?php

namespace App\Services\Blockchain;

use App\Enums\WalletSourceType;
use App\Services\Blockchain\Contracts\BlockchainSyncProvider;
use RuntimeException;

class BlockchainSyncProviderRegistry
{
    /** @var array<string, BlockchainSyncProvider> */
    private array $providers = [];

    /**
     * @param  iterable<BlockchainSyncProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function resolve(ChainConfig $chain, WalletSourceType $walletSourceType): BlockchainSyncProvider
    {
        $providerName = $chain->providerFor($walletSourceType);
        $provider = $this->providers[$providerName] ?? null;

        if ($provider === null) {
            throw new RuntimeException(
                "Kein Sync-Provider für Chain „{$chain->slug}“ konfiguriert.",
            );
        }

        if (! $provider->supports($chain, $walletSourceType)) {
            throw new RuntimeException(
                "Provider „{$providerName}“ unterstützt diesen Wallet-Typ nicht.",
            );
        }

        if (! $provider->isConfigured()) {
            throw new RuntimeException(
                "Provider „{$providerName}“ ist nicht konfiguriert. Bitte API-Schlüssel in der backend/.env setzen.",
            );
        }

        return $provider;
    }
}
