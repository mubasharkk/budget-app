<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Contract>
     */
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => User::factory(),
            'provider_id' => null,
            'category_id' => null,
            'name' => fake()->randomElement(['Rent', 'Internet', 'Mobile', 'Gym', 'Streaming', 'Insurance']),
            'description' => null,
            'amount' => fake()->randomFloat(2, 5, 1500),
            'currency' => 'EUR',
            'billing_cycle' => fake()->randomElement(BillingCycle::cases()),
            'billing_day' => fake()->numberBetween(1, 28),
            'start_date' => $start,
            'end_date' => null,
            'next_billing_date' => fake()->dateTimeBetween('now', '+1 month'),
            'status' => ContractStatus::Active,
            'auto_renew' => true,
            'notes' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContractStatus::Cancelled,
        ]);
    }
}
