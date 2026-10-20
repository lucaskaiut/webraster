<?php

namespace Database\Factories;

use App\Modules\Commission\Enums\CommissionStatus;
use App\Modules\Commission\Models\Commission;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'service_order_id' => ServiceOrder::factory(),
            'technician_id' => null,
            'status' => CommissionStatus::FORECAST,
            'base_amount_cents' => 0,
            'amount_cents' => 0,
            'breakdown' => [],
            'finalized_at' => null,
        ];
    }

    public function forOrder(ServiceOrder $order): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $order->tenant_id,
            'service_order_id' => $order->getKey(),
            'technician_id' => $order->technician_id,
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'status' => CommissionStatus::FINALIZED,
            'finalized_at' => now(),
        ]);
    }
}
