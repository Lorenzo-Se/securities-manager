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

    public function resolve(string $cryptoId, ?string $walletSource = null): ?SyncableCoin
    {
        foreach ($this->chains() as $slug => $chain) {
            if ($chain->nativeCoingeckoId === $cryptoId) {
                if ($walletSource !== null && ! $this->addressMatchesChain($walletSource, $chain)) {
                    continue;
                }

                return new SyncableCoin(
                    cryptoId: $cryptoId,
                    chainSlug: $slug,
                    walletSourceType: $this->walletSourceTypeForChain($chain, $walletSource),
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
        $platformPriority = config('crypto.platform_priority', []);

        foreach ($platformPriority as $platform) {
            $contract = $platforms[$platform] ?? '';

            if ($contract === '') {
                continue;
            }

            foreach ($this->chains() as $slug => $chain) {
                if ($chain->coingeckoPlatform !== $platform) {
                    continue;
                }

                if ($walletSource !== null && ! $this->addressMatchesPlatform($walletSource, $platform)) {
                    continue;
                }

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
        $walletSource = trim($walletSource);

        if ($walletSource === '') {
            return 'Wallet-Quelle ist erforderlich.';
        }

        foreach ($this->chains() as $slug => $chain) {
            if ($chain->nativeCoingeckoId === $cryptoId
                && $chain->model === ChainModel::Utxo
                && $slug !== 'bitcoin'
                && preg_match('/^(xpub|ypub|zpub|tpub|upub|vpub)/', $walletSource)) {
                return 'xpub-Sync wird für diese Chain nicht unterstützt. Bitte eine Adresse angeben.';
            }
        }

        $syncable = $this->resolve($cryptoId, $walletSource);

        if ($syncable === null) {
            return 'Diese Kryptowährung wird für On-Chain-Sync nicht unterstützt oder die Adresse passt nicht zur Chain.';
        }

        if ($syncable->walletSourceType === WalletSourceType::Xpub) {
            $chain = $this->chainForSlug($syncable->chainSlug);

            if ($chain !== null && $chain->model === ChainModel::Utxo && $chain->slug !== 'bitcoin') {
                return 'xpub-Sync wird für diese Chain nicht unterstützt. Bitte eine Adresse angeben.';
            }

            if (! preg_match('/^(xpub|ypub|zpub|tpub|upub|vpub)[1-9A-HJ-NP-Za-km-z]{80,}$/', $walletSource)) {
                return 'Ungültiger Extended Public Key (xpub).';
            }

            return null;
        }

        $chain = $this->chainForSlug($syncable->chainSlug);

        if ($chain === null) {
            return 'Chain-Konfiguration nicht gefunden.';
        }

        if ($chain->model === ChainModel::Utxo && $chain->slug !== 'bitcoin') {
            if (preg_match('/^(xpub|ypub|zpub|tpub|upub|vpub)/', $walletSource)) {
                return 'xpub-Sync wird für diese Chain nicht unterstützt. Bitte eine Adresse angeben.';
            }
        }

        return $this->validateAddressFormat($walletSource, $chain, $syncable->tokenContract !== null);
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

        /** @var array<string, array{provider: string, providers?: array<string, string>, provider_options?: array<string, string>, model: string, native_coingecko_id: string, decimals: int, coingecko_platform: string}> $configured */
        $configured = config('crypto.chains', []);

        foreach ($configured as $slug => $chain) {
            $this->chains[$slug] = new ChainConfig(
                slug: $slug,
                provider: $chain['provider'],
                providers: $chain['providers'] ?? [],
                providerOptions: $chain['provider_options'] ?? [],
                model: ChainModel::from($chain['model']),
                nativeCoingeckoId: $chain['native_coingecko_id'],
                decimals: $chain['decimals'],
                coingeckoPlatform: $chain['coingecko_platform'],
            );
        }

        return $this->chains;
    }

    private function walletSourceTypeForChain(ChainConfig $chain, ?string $walletSource): WalletSourceType
    {
        if ($chain->model === ChainModel::Utxo) {
            if ($walletSource !== null && ! preg_match('/^(xpub|ypub|zpub|tpub|upub|vpub)/', $walletSource)) {
                return WalletSourceType::Address;
            }

            return WalletSourceType::Xpub;
        }

        return WalletSourceType::Address;
    }

    private function addressMatchesChain(string $walletSource, ChainConfig $chain): bool
    {
        if ($chain->model === ChainModel::Utxo && $chain->slug === 'bitcoin') {
            return true;
        }

        if ($chain->coingeckoPlatform !== '') {
            return $this->addressMatchesPlatform($walletSource, $chain->coingeckoPlatform);
        }

        return $this->validateAddressFormat($walletSource, $chain, false) === null;
    }

    private function addressMatchesPlatform(string $walletSource, string $platform): bool
    {
        return match ($platform) {
            'ethereum', 'binance-smart-chain' => (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $walletSource),
            'tron' => (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $walletSource),
            'solana' => $this->isSolanaAddress($walletSource),
            'cardano' => (bool) preg_match('/^(addr1|stake1)[a-z0-9]+$/', $walletSource),
            default => true,
        };
    }

    private function validateAddressFormat(string $walletSource, ChainConfig $chain, bool $isToken): ?string
    {
        return match ($chain->slug) {
            'ethereum', 'binance-smart-chain', 'hyperliquid' => preg_match('/^0x[a-fA-F0-9]{40}$/', $walletSource)
                ? null
                : 'Ungültige EVM-Adresse (0x…).',
            'tron' => preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $walletSource)
                ? null
                : 'Ungültige Tron-Adresse (T…).',
            'solana' => $this->isSolanaAddress($walletSource)
                ? null
                : 'Ungültige Solana-Adresse.',
            'ripple' => preg_match('/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/', $walletSource)
                ? null
                : 'Ungültige XRP-Adresse (r…).',
            'cardano' => preg_match('/^(addr1|stake1)[a-z0-9]+$/', $walletSource)
                ? null
                : 'Ungültige Cardano-Adresse (addr1…).',
            'litecoin', 'dogecoin', 'zcash' => strlen($walletSource) >= 26 && strlen($walletSource) <= 95
                ? null
                : 'Ungültige Wallet-Adresse.',
            default => strlen($walletSource) >= 20 && strlen($walletSource) <= 120
                ? null
                : 'Ungültige Wallet-Adresse.',
        };
    }

    private function isSolanaAddress(string $walletSource): bool
    {
        if (str_starts_with($walletSource, '0x') || str_starts_with($walletSource, 'r') || str_starts_with($walletSource, 'T')) {
            return false;
        }

        return (bool) preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $walletSource);
    }
}
