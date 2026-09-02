<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\WalletSourceType;
use App\Support\WalletSourceMasker;
use Database\Factories\PortfolioFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'crypto_id',
    'crypto_symbol',
    'crypto_name',
    'wallet_source_type',
    'wallet_source',
    'chain_slug',
    'token_contract',
    'last_synced_at',
    'sync_status',
    'sync_error',
])]
class Portfolio extends Model
{
    /** @use HasFactory<PortfolioFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_source' => 'encrypted',
            'wallet_source_type' => WalletSourceType::class,
            'sync_status' => SyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

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

    public function maskedWalletSource(): ?string
    {
        return WalletSourceMasker::mask($this->wallet_source);
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
