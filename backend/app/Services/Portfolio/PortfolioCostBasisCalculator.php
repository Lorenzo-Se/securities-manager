<?php

namespace App\Services\Portfolio;

use App\Models\PortfolioMovement;
use Illuminate\Support\Collection;

class PortfolioCostBasisCalculator
{
    /**
     * @param  Collection<int, PortfolioMovement>  $movements
     */
    public function costBasisAtTimestamp(Collection $movements, int $timestamp): ?string
    {
        $relevant = $movements->filter(
            static fn (PortfolioMovement $movement): bool => ($movement->occurred_at?->getTimestamp() ?? 0) <= $timestamp,
        );

        return $this->calculate($relevant);
    }

    /**
     * @param  Collection<int, PortfolioMovement>  $movements
     */
    public function calculate(Collection $movements): ?string
    {
        if ($movements->isEmpty()) {
            return '0.00000000';
        }

        $sorted = $movements
            ->sortBy([
                ['occurred_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $holdings = 0.0;
        $costUsd = 0.0;

        foreach ($sorted as $movement) {
            if ($movement->unit_price_usd === null) {
                return null;
            }

            $amount = (float) $movement->amount;
            $unitPriceUsd = (float) $movement->unit_price_usd;

            if ($movement->type === 'deposit') {
                $costUsd += $amount * $unitPriceUsd;
                $holdings += $amount;

                continue;
            }

            if ($holdings <= 0) {
                return null;
            }

            $averageCost = $costUsd / $holdings;
            $costUsd -= $amount * $averageCost;
            $holdings -= $amount;
        }

        return number_format(max($costUsd, 0), 8, '.', '');
    }
}
