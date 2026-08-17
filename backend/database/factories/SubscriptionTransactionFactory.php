<?php

namespace Database\Factories;

use App\Domain\Subscription\Models\SubscriptionTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionTransaction>
 */
class SubscriptionTransactionFactory extends Factory
{
    protected $model = SubscriptionTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->numberBetween(35000, 170000),
            'currency' => 'TZS',
            'billing_cycle' => 'monthly',
            'status' => SubscriptionTransaction::STATUS_PAID,
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'mobile_money']),
            'notes' => null,
            'paid_at' => now(),
        ];
    }
}
