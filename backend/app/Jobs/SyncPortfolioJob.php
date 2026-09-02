<?php

namespace App\Jobs;

use App\Models\Portfolio;
use App\Services\Blockchain\PortfolioSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPortfolioJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $portfolioId,
    ) {}

    public function handle(PortfolioSyncService $syncService): void
    {
        $portfolio = Portfolio::query()->find($this->portfolioId);

        if ($portfolio === null) {
            return;
        }

        try {
            $syncService->sync($portfolio);
        } catch (Throwable $exception) {
            // sync_status and sync_error are persisted by PortfolioSyncService.
            Log::warning('SyncPortfolioJob failed', [
                'portfolio_id' => $this->portfolioId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
