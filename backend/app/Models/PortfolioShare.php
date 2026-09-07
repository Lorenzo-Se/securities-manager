<?php

namespace App\Models;

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use Database\Factories\PortfolioShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'portfolio_id',
    'invited_by_user_id',
    'invited_email',
    'invited_user_id',
    'share_type',
    'share_amount',
    'share_percentage',
    'show_total_balance',
    'status',
    'accepted_at',
    'declined_at',
])]
class PortfolioShare extends Model
{
    /** @use HasFactory<PortfolioShareFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'share_type' => PortfolioShareType::class,
            'share_amount' => 'decimal:8',
            'share_percentage' => 'decimal:2',
            'show_total_balance' => 'boolean',
            'status' => PortfolioShareStatus::class,
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            PortfolioShareStatus::Pending,
            PortfolioShareStatus::Accepted,
        ], true);
    }
}
