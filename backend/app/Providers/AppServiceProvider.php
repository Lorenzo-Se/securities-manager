<?php

namespace App\Providers;

use App\Services\Blockchain\BlockchainSyncProviderRegistry;
use App\Services\Blockchain\Clients\EtherscanClient;
use App\Services\Blockchain\Providers\BlockchainInfoSyncProvider;
use App\Services\Blockchain\Providers\BlockfrostSyncProvider;
use App\Services\Blockchain\Providers\BscScanSyncProvider;
use App\Services\Blockchain\Providers\EtherscanSyncProvider;
use App\Services\Blockchain\Providers\HeliusSyncProvider;
use App\Services\Blockchain\Providers\HyperliquidSyncProvider;
use App\Services\Blockchain\Providers\MempoolSyncProvider;
use App\Services\Blockchain\Providers\SoChainSyncProvider;
use App\Services\Blockchain\Providers\TronGridSyncProvider;
use App\Services\Blockchain\Providers\XrplSyncProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EtherscanClient::class, function (): EtherscanClient {
            return new EtherscanClient(config('crypto.etherscan'));
        });

        $this->app->tag([
            MempoolSyncProvider::class,
            BlockchainInfoSyncProvider::class,
            EtherscanSyncProvider::class,
            BscScanSyncProvider::class,
            TronGridSyncProvider::class,
            HeliusSyncProvider::class,
            BlockfrostSyncProvider::class,
            SoChainSyncProvider::class,
            XrplSyncProvider::class,
            HyperliquidSyncProvider::class,
        ], 'blockchain.sync_providers');

        $this->app->singleton(BlockchainSyncProviderRegistry::class, function ($app): BlockchainSyncProviderRegistry {
            return new BlockchainSyncProviderRegistry($app->tagged('blockchain.sync_providers'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
