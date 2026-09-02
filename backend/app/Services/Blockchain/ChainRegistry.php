<?php

namespace App\Services\Blockchain;

use App\Enums\ChainModel;
use App\Enums\WalletSourceType;
use App\Services\Crypto\CoinGeckoClient;

class ChainRegistry
{
    /** @var array<string, ChainConfig>|null */
    private ?array $chains = null;

    public function __construct(
        private readonly CoinGeckoClient $coinGecko,
    ) {}

    public function resolve(string $cryptoId): ?SyncableCoin
    {
        foreach ($this->chains() as $slug => $chain) {
            if ($chain->nativeCoingeckoId === $cryptoId) {
                return new SyncableCoin(
                    cryptoId: $cryptoId,
                    chainSlug: $slug,
                    walletSourceType: $chain->walletSourceType(),
                    tokenContract: null,
                );
            }
        }

        $detail = $this->coinGecko->getCoinDetail($cryptoId);

        if ($detail === null) {
            return null;
        }

        /** @var array<string, string> $platforms */
        $platforms = $detail['platforms'] ?? [];

        foreach ($this->chains() as $slug => $chain) {
            if ($chain->coingeckoPlatform === '') {
                continue;
            }

            $contract = $platforms[$chain->coingeckoPlatform] ?? '';

            if ($contract !== '') {
                return new SyncableCoin(
                    cryptoId: $cryptoId,
                    chainSlug: $slug,
                    walletSourceType: WalletSourceType::Address,
                    tokenContract: $contract,
                );
            }
        }

        return null;
    }

    public function chainForSlug(string $slug): ?ChainConfig
    {
        return $this->chains()[$slug] ?? null;
    }

    public function isSyncSupported(string $cryptoId): bool
    {
        return $this->resolve($cryptoId) !== null;
    }

    public function walletSourceTypeFor(string $cryptoId): ?WalletSourceType
    {
        return $this->resolve($cryptoId)?->walletSourceType;
    }

    public function validateWalletSource(string $cryptoId, string $walletSource): ?string
    {
        $syncable = $this->resolve($cryptoId);

        if ($syncable === null) {
            return 'Diese Kryptowährung wird für On-Chain-Sync nicht unterstützt.';
        }

        $walletSource = trim($walletSource);

        if ($walletSource === '') {
            return 'Wallet-Quelle ist erforderlich.';
        }

        if ($syncable->walletSourceType === WalletSourceType::Xpub) {
            if (! preg_match('/^(xpub|ypub|zpub|tpub|upub|vpub)[1-9A-HJ-NP-Za-km-z]{80,}$/', $walletSource)) {
                return 'Ungültiger Extended Public Key (xpub).';
            }

            return null;
        }

        if (strlen($walletSource) < 20 || strlen($walletSource) > 120) {
            return 'Ungültige Wallet-Adresse.';
        }

        return null;
    }

    /**
     * @return array<string, ChainConfig>
     */
    private function chains(): array
    {
        if ($this->chains !== null) {
            return $this->chains;
        }

        $this->chains = [];

        /** @var array<string, array{blockchair_slug: string, model: string, native_coingecko_id: string, decimals: int, coingecko_platform: string}> $configured */
        $configured = config('crypto.chains', []);

        foreach ($configured as $slug => $chain) {
            $this->chains[$slug] = new ChainConfig(
                blockchairSlug: $chain['blockchair_slug'],
                model: ChainModel::from($chain['model']),
                nativeCoingeckoId: $chain['native_coingecko_id'],
                decimals: $chain['decimals'],
                coingeckoPlatform: $chain['coingecko_platform'],
            );
        }

        return $this->chains;
    }
}
