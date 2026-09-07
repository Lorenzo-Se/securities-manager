<?php

namespace App\Services\Portfolio;

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use App\Models\Portfolio;
use App\Models\PortfolioShare;
use App\Models\User;

class PortfolioAccessService
{
    public function isOwner(Portfolio $portfolio, User $user): bool
    {
        return $portfolio->user_id === $user->id;
    }

    public function canView(Portfolio $portfolio, User $user): bool
    {
        if ($this->isOwner($portfolio, $user)) {
            return true;
        }

        return $this->getAcceptedShare($portfolio, $user) !== null;
    }

    public function canManage(Portfolio $portfolio, User $user): bool
    {
        return $this->isOwner($portfolio, $user);
    }

    public function getAcceptedShare(Portfolio $portfolio, User $user): ?PortfolioShare
    {
        return PortfolioShare::query()
            ->where('portfolio_id', $portfolio->id)
            ->where('invited_user_id', $user->id)
            ->where('status', PortfolioShareStatus::Accepted)
            ->first();
    }

    public function computeShareAmount(Portfolio $portfolio, PortfolioShare $share): string
    {
        if ($share->share_type === PortfolioShareType::Amount) {
            return number_format((float) $share->share_amount, 8, '.', '');
        }

        if ($share->share_type === PortfolioShareType::Percentage) {
            $balance = (float) $portfolio->balance;
            $percentage = (float) $share->share_percentage;

            return number_format($balance * $percentage / 100, 8, '.', '');
        }

        return $portfolio->balance;
    }

    public function shouldHideTotalBalance(?PortfolioShare $share): bool
    {
        if ($share === null) {
            return false;
        }

        if ($share->share_type === PortfolioShareType::Viewer) {
            return false;
        }

        return ! $share->show_total_balance;
    }

    public function canViewMovements(Portfolio $portfolio, User $user): bool
    {
        if ($this->isOwner($portfolio, $user)) {
            return true;
        }

        $share = $this->getAcceptedShare($portfolio, $user);

        if ($share === null) {
            return false;
        }

        if ($share->share_type === PortfolioShareType::Viewer) {
            return true;
        }

        return $share->show_total_balance;
    }

    public function computeShareCostBasis(
        Portfolio $portfolio,
        PortfolioShare $share,
        ?string $totalCostBasisUsd,
    ): ?string {
        if ($totalCostBasisUsd === null) {
            return null;
        }

        $total = (float) $totalCostBasisUsd;

        if ($share->share_type === PortfolioShareType::Percentage) {
            return number_format($total * (float) $share->share_percentage / 100, 8, '.', '');
        }

        if ($share->share_type === PortfolioShareType::Amount) {
            $balance = (float) $portfolio->balance;

            if ($balance <= 0) {
                return null;
            }

            return number_format($total * (float) $share->share_amount / $balance, 8, '.', '');
        }

        return number_format($total, 8, '.', '');
    }
}
