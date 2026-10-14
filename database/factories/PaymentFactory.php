<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'plan_id' => null,
            'amount_cents' => 2990,
            'currency' => 'BRL',
            'method' => PaymentMethod::Pix,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'note' => null,
            'gateway' => 'manual',
            'gateway_ref' => null,
        ];
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Refunded,
        ]);
    }
}
