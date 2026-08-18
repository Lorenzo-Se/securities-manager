<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioMovement>
 */
class PortfolioMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'type' => 'deposit',
            'amount' => fake()->randomFloat(8, 0.00000001, 10),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn () => ['type' => 'deposit']);
    }

    public function withdrawal(): static
    {
        return $this->state(fn () => ['type' => 'withdrawal']);
    }
}
