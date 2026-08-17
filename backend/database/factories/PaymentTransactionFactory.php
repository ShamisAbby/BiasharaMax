<?php

namespace Database\Factories;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(35000, 170000);

        return [
            'type' => PaymentTransaction::TYPE_SUBSCRIPTION_PAYMENT,
            'reference_number' => 'TXN-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'currency' => 'TZS',
            'tax_amount' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'commission_amount' => 0,
            'refunded_amount' => 0,
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'mobile_money']),
            'paid_at' => now(),
        ];
    }
}
