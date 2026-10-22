<?php

namespace Database\Factories;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => 'mercadopago',
            'event_id' => (string) fake()->unique()->numerify('########'),
            'topic' => 'payment',
            'action' => 'payment.created',
            'resource_id' => (string) fake()->numerify('##########'),
            'request_id' => fake()->uuid(),
            'status' => WebhookEventStatus::Pending,
            'attempts' => 0,
            'payload' => ['type' => 'payment', 'data' => ['id' => '1']],
            'error' => null,
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookEventStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookEventStatus::Failed,
            'error' => 'gateway unavailable',
        ]);
    }
}
