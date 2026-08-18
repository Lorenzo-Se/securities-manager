<?php

namespace App\Models;

use Database\Factories\PortfolioMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'portfolio_id',
    'type',
    'amount',
    'unit_price_usd',
    'occurred_at',
])]
class PortfolioMovement extends Model
{
    /** @use HasFactory<PortfolioMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'unit_price_usd' => 'decimal:8',
            'occurred_at' => 'datetime',
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
