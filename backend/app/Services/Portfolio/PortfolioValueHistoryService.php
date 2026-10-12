<?php

namespace App\Services\Portfolio;

use App\Enums\PortfolioShareType;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Models\PortfolioShare;
use App\Models\User;
use App\Services\Crypto\CoinGeckoClient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PortfolioValueHistoryService
{
    public function __construct(
        private readonly CoinGeckoClient $coinGecko,
        private readonly PortfolioAccessService $accessService,
        private readonly PortfolioCostBasisCalculator $costBasisCalculator,
    ) {}

    /**
     * @return array{
     *     currency: string,
     *     days: int|string,
     *     points: list<array{
     *         date: string,
     *         value: string,
     *         profit_amount: string|null,
     *         profit_percent: string|null
     *     }>
     * }
     */
    public function build(Portfolio $portfolio, User $user, int|string $days): array
    {
        $currency = $user->preferredCurrency();
        $movements = $portfolio->movements()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return [
                'currency' => $currency,
                'days' => $days,
                'points' => [],
            ];
        }

        $balanceEvents = $this->buildBalanceEvents($movements);
        $share = $this->accessService->isOwner($portfolio, $user)
            ? null
            : $this->accessService->getAcceptedShare($portfolio, $user);

        $firstMovementAt = $movements->first()->occurred_at ?? now();
        $chartDays = $this->resolveChartDays($days, $firstMovementAt);

        $chart = $this->coinGecko->getMarketChart($portfolio->crypto_id, $chartDays, $currency);
        $usdChart = $this->coinGecko->getMarketChart($portfolio->crypto_id, $chartDays, 'usd');
        $usdPricesByTimestamp = collect($usdChart)->keyBy('timestamp');
        $firstMovementTimestamp = $firstMovementAt->getTimestamp();

        $points = [];

        foreach ($chart as $point) {
            if ($point['timestamp'] < $firstMovementTimestamp * 1000) {
                continue;
            }

            $timestamp = (int) ($point['timestamp'] / 1000);
            $balance = $this->balanceAtTimestamp($balanceEvents, $timestamp);

            if ($balance <= 0) {
                continue;
            }

            $effectiveBalance = $this->effectiveBalance($balance, $share);
            $value = $effectiveBalance * $point['price'];
            $profit = $this->calculateProfit(
                $movements,
                $timestamp,
                $value,
                $balance,
                $share,
                $point['price'],
                $usdPricesByTimestamp->get($point['timestamp']),
            );

            $points[] = [
                'date' => CarbonImmutable::createFromTimestampMs($point['timestamp'])->toIso8601String(),
                'value' => number_format($value, 2, '.', ''),
                'profit_amount' => $profit['amount'],
                'profit_percent' => $profit['percent'],
            ];
        }

        return [
            'currency' => $currency,
            'days' => $days,
            'points' => $points,
        ];
    }

    private function resolveChartDays(int|string $days, CarbonInterface $firstMovementAt): int
    {
        if ($days !== 'max') {
            return (int) $days;
        }

        $daysSinceFirst = (int) ceil($firstMovementAt->diffInSeconds(now()) / 86_400);

        return max(1, $daysSinceFirst + 1);
    }

    /**
     * @param  Collection<int, PortfolioMovement>  $movements
     * @return array{amount: string|null, percent: string|null}
     */
    private function calculateProfit(
        Collection $movements,
        int $timestamp,
        float $value,
        float $balance,
        ?PortfolioShare $share,
        float $priceUser,
        ?array $usdPoint,
    ): array {
        $costBasisUsd = $this->costBasisCalculator->costBasisAtTimestamp($movements, $timestamp);

        if ($costBasisUsd === null) {
            return ['amount' => null, 'percent' => null];
        }

        $effectiveCostBasisUsd = $this->effectiveCostBasis((float) $costBasisUsd, $balance, $share);

        if ($effectiveCostBasisUsd === null) {
            return ['amount' => null, 'percent' => null];
        }

        $priceUsd = is_array($usdPoint) ? (float) ($usdPoint['price'] ?? 0) : 0.0;

        if ($priceUsd <= 0) {
            return ['amount' => null, 'percent' => null];
        }

        $costBasisUser = $effectiveCostBasisUsd * ($priceUser / $priceUsd);
        $profitAmount = $value - $costBasisUser;
        $profitPercent = $costBasisUser > 0
            ? ($profitAmount / $costBasisUser) * 100
            : 0.0;

        return [
            'amount' => number_format($profitAmount, 2, '.', ''),
            'percent' => number_format($profitPercent, 2, '.', ''),
        ];
    }

    /**
     * @param  Collection<int, PortfolioMovement>  $movements
     * @return list<array{timestamp: int, balance: float}>
     */
    private function buildBalanceEvents($movements): array
    {
        $balance = 0.0;
        $events = [];

        foreach ($movements as $movement) {
            $amount = (float) $movement->amount;

            if ($movement->type === 'deposit') {
                $balance += $amount;
            } else {
                $balance -= $amount;
            }

            $events[] = [
                'timestamp' => $movement->occurred_at?->getTimestamp() ?? 0,
                'balance' => max(0.0, $balance),
            ];
        }

        return $events;
    }

    /**
     * @param  list<array{timestamp: int, balance: float}>  $balanceEvents
     */
    private function balanceAtTimestamp(array $balanceEvents, int $timestamp): float
    {
        $balance = 0.0;

        foreach ($balanceEvents as $event) {
            if ($event['timestamp'] <= $timestamp) {
                $balance = $event['balance'];
            }
        }

        return $balance;
    }

    private function effectiveBalance(float $balance, ?PortfolioShare $share): float
    {
        if ($share === null || $share->share_type === PortfolioShareType::Viewer) {
            return $balance;
        }

        if ($share->share_type === PortfolioShareType::Amount) {
            return min((float) $share->share_amount, $balance);
        }

        if ($share->share_type === PortfolioShareType::Percentage) {
            return $balance * (float) $share->share_percentage / 100;
        }

        return $balance;
    }

    private function effectiveCostBasis(
        float $totalCostBasisUsd,
        float $balance,
        ?PortfolioShare $share,
    ): ?float {
        if ($share === null || $share->share_type === PortfolioShareType::Viewer) {
            return $totalCostBasisUsd;
        }

        if ($share->share_type === PortfolioShareType::Percentage) {
            return $totalCostBasisUsd * (float) $share->share_percentage / 100;
        }

        if ($share->share_type === PortfolioShareType::Amount) {
            if ($balance <= 0) {
                return null;
            }

            return $totalCostBasisUsd * (float) $share->share_amount / $balance;
        }

        return $totalCostBasisUsd;
    }
}
