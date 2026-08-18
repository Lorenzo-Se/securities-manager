<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Portfolio>
 */
class PortfolioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'crypto_id' => 'bitcoin',
            'crypto_symbol' => 'btc',
            'crypto_name' => 'Bitcoin',
        ];
    }
}
