<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PortfolioShareStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'keycloak_id', 'currency'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function portfolioShares(): HasMany
    {
        return $this->hasMany(PortfolioShare::class, 'invited_user_id');
    }

    public function sharedPortfolios(): HasManyThrough
    {
        return $this->hasManyThrough(
            Portfolio::class,
            PortfolioShare::class,
            'invited_user_id',
            'id',
            'id',
            'portfolio_id',
        )->where('portfolio_shares.status', PortfolioShareStatus::Accepted->value);
    }

    public function preferredCurrency(): string
    {
        return $this->currency ?? (string) config('crypto.coingecko.default_currency');
    }
}
