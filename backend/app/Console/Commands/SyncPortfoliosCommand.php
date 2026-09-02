<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Jobs\SyncPortfolioJob;
use App\Models\Portfolio;
use Illuminate\Console\Command;

class SyncPortfoliosCommand extends Command
{
    protected $signature = 'portfolios:sync';

    protected $description = 'Synchronize on-chain transactions for all portfolios';

    public function handle(): int
    {
        $portfolios = Portfolio::query()
            ->whereNotNull('wallet_source')
            ->where('sync_status', '!=', SyncStatus::Syncing)
            ->get();

        foreach ($portfolios as $portfolio) {
            SyncPortfolioJob::dispatch($portfolio->id);
        }

        $this->info("Dispatched sync jobs for {$portfolios->count()} portfolios.");

        return self::SUCCESS;
    }
}
