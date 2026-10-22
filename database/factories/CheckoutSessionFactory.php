<?php

namespace Database\Factories;

use App\Enums\CheckoutMode;
use App\Enums\CheckoutStatus;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'mode' => CheckoutMode::OneTime,
            'amount_cents' => 890,
            'currency' => 'BRL',
            'status' => CheckoutStatus::Pending,
            'gateway' => 'mercadopago',
            'gateway_ref' => null,
            'gateway_payment_id' => null,
            'payment_id' => null,
            'subscription_id' => null,
            'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=SMOKE',
            'expires_at' => now()->addHour(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CheckoutStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CheckoutStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => CheckoutMode::Recurring,
            'init_point' => null,
        ]);
    }
}
