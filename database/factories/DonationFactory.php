<?php

namespace Database\Factories;

use App\Enums\DonationStatus;
use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => null,
            'email' => null,
            'locale' => 'pt_BR',
            'amount_cents' => 1000,
            'currency' => 'BRL',
            'status' => DonationStatus::Pending,
            'gateway' => 'mercadopago',
            'gateway_ref' => null,
            'qr_code' => '00020126...6304ABCD',
            'qr_code_base64' => base64_encode('png-fake'),
            'ticket_url' => null,
            'expires_at' => now()->addHour(),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DonationStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DonationStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function staticPix(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gateway' => 'manual',
        ]);
    }
}
