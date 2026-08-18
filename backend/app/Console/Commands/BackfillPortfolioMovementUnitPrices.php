<?php

namespace App\Console\Commands;

use App\Models\PortfolioMovement;
use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Console\Command;

class BackfillPortfolioMovementUnitPrices extends Command
{
    protected $signature = 'portfolios:backfill-unit-prices';

    protected $description = 'Backfill missing unit_price_usd values for portfolio movements';

    public function handle(CoinGeckoClient $coinGecko): int
    {
        $movements = PortfolioMovement::query()
            ->with('portfolio')
            ->whereNull('unit_price_usd')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            $this->info('No movements require backfill.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($movements as $movement) {
            $portfolio = $movement->portfolio;

            if ($portfolio === null || $movement->occurred_at === null) {
                $this->warn("Skipping movement {$movement->id}: missing portfolio or occurred_at.");

                continue;
            }

            try {
                $unitPriceUsd = $coinGecko->getHistoricalPrice(
                    $portfolio->crypto_id,
                    $movement->occurred_at,
                    'usd',
                );

                $movement->update([
                    'unit_price_usd' => number_format($unitPriceUsd, 8, '.', ''),
                ]);

                $updated++;
                $this->line("Updated movement {$movement->id}.");
            } catch (\Throwable $exception) {
                $this->error("Failed movement {$movement->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Backfilled {$updated} movement(s).");

        return self::SUCCESS;
    }
}
