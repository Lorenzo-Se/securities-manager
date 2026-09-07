<?php

namespace Database\Factories;

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use App\Models\Portfolio;
use App\Models\PortfolioShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioShare>
 */
class PortfolioShareFactory extends Factory
{
    protected $model = PortfolioShare::class;

    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'invited_by_user_id' => User::factory(),
            'invited_email' => fake()->unique()->safeEmail(),
            'invited_user_id' => null,
            'share_type' => PortfolioShareType::Viewer,
            'share_amount' => null,
            'share_percentage' => null,
            'show_total_balance' => false,
            'status' => PortfolioShareStatus::Pending,
            'accepted_at' => null,
            'declined_at' => null,
        ];
    }

    public function viewer(): static
    {
        return $this->state(fn () => [
            'share_type' => PortfolioShareType::Viewer,
            'share_amount' => null,
            'share_percentage' => null,
        ]);
    }

    public function amount(string $amount = '0.50000000'): static
    {
        return $this->state(fn () => [
            'share_type' => PortfolioShareType::Amount,
            'share_amount' => $amount,
            'share_percentage' => null,
        ]);
    }

    public function percentage(string $percentage = '25.00'): static
    {
        return $this->state(fn () => [
            'share_type' => PortfolioShareType::Percentage,
            'share_amount' => null,
            'share_percentage' => $percentage,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PortfolioShareStatus::Pending,
            'accepted_at' => null,
            'declined_at' => null,
        ]);
    }

    public function accepted(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $invitedUser = $user ?? User::factory()->create([
                'email' => $attributes['invited_email'],
            ]);

            return [
                'status' => PortfolioShareStatus::Accepted,
                'invited_user_id' => $invitedUser->id,
                'accepted_at' => now(),
                'declined_at' => null,
            ];
        });
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => PortfolioShareStatus::Declined,
            'accepted_at' => null,
            'declined_at' => now(),
        ]);
    }
}
