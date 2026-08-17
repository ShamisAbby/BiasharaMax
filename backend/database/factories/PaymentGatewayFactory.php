<?php

namespace Database\Factories;

use App\Domain\Finance\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'provider' => fake()->randomElement([
                PaymentGateway::PROVIDER_STRIPE,
                PaymentGateway::PROVIDER_FLUTTERWAVE,
                PaymentGateway::PROVIDER_MPESA,
            ]),
            'is_enabled' => false,
            'mode' => PaymentGateway::MODE_SANDBOX,
            'credentials' => null,
            'supported_currencies' => ['TZS', 'USD'],
            'supported_countries' => ['TZ'],
            'fee_percentage' => 2.50,
            'fee_fixed' => 0,
            'priority' => 0,
            'health_status' => PaymentGateway::HEALTH_UNKNOWN,
            'sort_order' => 0,
        ];
    }
}
