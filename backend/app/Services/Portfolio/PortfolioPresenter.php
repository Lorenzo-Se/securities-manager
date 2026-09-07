<?php

namespace App\Services\Portfolio;

use App\Enums\PortfolioShareType;
use App\Enums\SyncStatus;
use App\Models\Portfolio;
use App\Models\PortfolioShare;
use App\Models\User;

class PortfolioPresenter
{
    public function __construct(
        private readonly PortfolioCostBasisCalculator $costBasisCalculator,
        private readonly PortfolioAccessService $accessService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function format(Portfolio $portfolio, User $user): array
    {
        $isOwner = $this->accessService->isOwner($portfolio, $user);
        $share = $isOwner ? null : $this->accessService->getAcceptedShare($portfolio, $user);
        $hideTotalBalance = $this->accessService->shouldHideTotalBalance($share);
        $totalCostBasisUsd = $this->costBasisCalculator->calculate($portfolio->movements);

        $costBasisUsd = $hideTotalBalance
            ? null
            : $totalCostBasisUsd;

        $data = [
            'id' => $portfolio->id,
            'name' => $portfolio->name,
            'crypto_id' => $portfolio->crypto_id,
            'crypto_symbol' => $portfolio->crypto_symbol,
            'crypto_name' => $portfolio->crypto_name,
            'wallet_source_type' => $portfolio->wallet_source_type?->value,
            'wallet_source' => $portfolio->maskedWalletSource(),
            'sync_status' => $portfolio->sync_status?->value ?? SyncStatus::Failed->value,
            'sync_error' => $isOwner ? $portfolio->sync_error : null,
            'last_synced_at' => $portfolio->last_synced_at?->toIso8601String(),
            'balance' => $hideTotalBalance ? null : $portfolio->balance,
            'cost_basis_usd' => $costBasisUsd,
            'created_at' => $portfolio->created_at?->toIso8601String(),
            'updated_at' => $portfolio->updated_at?->toIso8601String(),
            'access_role' => $isOwner ? 'owner' : 'viewer',
            'share' => $this->formatShare($portfolio, $share, $totalCostBasisUsd),
        ];

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatShare(
        Portfolio $portfolio,
        ?PortfolioShare $share,
        ?string $totalCostBasisUsd,
    ): ?array {
        if ($share === null) {
            return null;
        }

        $myShareAmount = null;

        if (in_array($share->share_type, [PortfolioShareType::Amount, PortfolioShareType::Percentage], true)) {
            $myShareAmount = $this->accessService->computeShareAmount($portfolio, $share);
        }

        $myShareCostBasisUsd = null;

        if (in_array($share->share_type, [PortfolioShareType::Amount, PortfolioShareType::Percentage], true)) {
            $myShareCostBasisUsd = $this->accessService->computeShareCostBasis(
                $portfolio,
                $share,
                $totalCostBasisUsd,
            );
        }

        return [
            'type' => $share->share_type->value,
            'amount' => $share->share_amount !== null
                ? number_format((float) $share->share_amount, 8, '.', '')
                : null,
            'percentage' => $share->share_percentage !== null
                ? number_format((float) $share->share_percentage, 2, '.', '')
                : null,
            'show_total_balance' => $share->show_total_balance,
            'my_share_amount' => $myShareAmount,
            'my_share_cost_basis_usd' => $myShareCostBasisUsd,
        ];
    }
}
