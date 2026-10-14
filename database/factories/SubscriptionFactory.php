<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Assinatura vigente, comecada hoje e valida por um mes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'gateway' => 'manual',
            'gateway_ref' => null,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }

    /**
     * Ativa no papel, vencida na pratica: e assim que uma assinatura expira sem
     * rotina agendada - o estado e derivado do periodo, nao gravado.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'current_period_start' => now()->subMonths(2),
            'current_period_end' => now()->subMonth(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Canceled,
        ]);
    }

    /**
     * Sem data de fim: concessao por prazo indeterminado.
     */
    public function lifetime(): static
    {
        return $this->state(fn (array $attributes): array => [
            'current_period_end' => null,
        ]);
    }
}
