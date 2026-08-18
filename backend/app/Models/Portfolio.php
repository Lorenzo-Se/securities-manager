<?php

namespace App\Models;

use Database\Factories\PortfolioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
