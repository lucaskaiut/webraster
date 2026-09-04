<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'gateway' => 'pix_mock',
            'amount' => '49.90',
            'status' => InvoiceStatus::PENDING,
            'payment_method' => PaymentMethod::PIX,
            'external_id' => 'pay_'.fake()->unique()->lexify('??????????'),
            'pix_code' => '00020126PIX',
            'pix_qrcode' => 'data:image/svg+xml;base64,abc',
            'due_date' => now(),
            'paid_at' => null,
            'expires_at' => now()->addDay(),
            'metadata' => null,
        ];
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'amount' => $subscription->plan?->price ?? '49.90',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
