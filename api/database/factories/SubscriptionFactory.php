<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'payment_gateway' => 'mockPix',
            'status' => SubscriptionStatus::TRIALING,
            'started_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'last_billed_at' => null,
            'next_billing_at' => now()->addDays(7),
            'cancelled_at' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (): array => [
            'plan_id' => $plan->getKey(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::ACTIVE,
            'trial_ends_at' => null,
            'last_billed_at' => now(),
            'next_billing_at' => now()->addDays(30),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::PAST_DUE,
            'next_billing_at' => now()->subDay(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::SUSPENDED,
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::ACTIVE,
            'trial_ends_at' => null,
            'next_billing_at' => now()->subMinute(),
        ]);
    }
}
