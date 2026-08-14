<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use OpenKOS\Core\Enums\PaymentStatus;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_id' => null,
            'gateway_key' => 'test-gateway',
            'reference' => fake()->unique()->uuid(),
            'provider_reference' => fake()->unique()->uuid(),
            'amount' => fake()->numberBetween(500_000, 3_000_000),
            'currency' => 'IDR',
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addHour(),
            'metadata' => ['source' => 'test'],
            'initiated_at' => now(),
            'settled_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'canceled_at' => null,
        ];
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Settled,
            'settled_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Expired,
            'expired_at' => now(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }
}
