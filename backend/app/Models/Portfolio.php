<?php

namespace App\Models;

use Database\Factories\PortfolioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use DateTimeInterface;

#[Fillable([
    'user_id',
    'name',
    'crypto_id',
    'crypto_symbol',
    'crypto_name',
])]
class Portfolio extends Model
{
    /** @use HasFactory<PortfolioFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PortfolioMovement::class);
    }

    public function balanceAt(DateTimeInterface $at): string
    {
        $movements = $this->movements()
            ->where('occurred_at', '<=', $at)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $deposits = $movements
            ->where('type', 'deposit')
            ->sum(fn (PortfolioMovement $movement) => (float) $movement->amount);

        $withdrawals = $movements
            ->where('type', 'withdrawal')
            ->sum(fn (PortfolioMovement $movement) => (float) $movement->amount);

        return number_format($deposits - $withdrawals, 8, '.', '');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function balance(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->relationLoaded('movements')) {
                $deposits = $this->movements
                    ->where('type', 'deposit')
                    ->sum(fn (PortfolioMovement $movement) => (float) $movement->amount);

                $withdrawals = $this->movements
                    ->where('type', 'withdrawal')
                    ->sum(fn (PortfolioMovement $movement) => (float) $movement->amount);

                return number_format($deposits - $withdrawals, 8, '.', '');
            }

            $deposits = $this->movements()->where('type', 'deposit')->sum('amount');
            $withdrawals = $this->movements()->where('type', 'withdrawal')->sum('amount');

            return number_format((float) $deposits - (float) $withdrawals, 8, '.', '');
        });
    }
}
