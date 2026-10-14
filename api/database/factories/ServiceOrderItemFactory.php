<?php

namespace Database\Factories;

use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderItem>
 */
class ServiceOrderItemFactory extends Factory
{
    protected $model = ServiceOrderItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'service_order_id' => ServiceOrder::factory(),
            'service_id' => null,
            'name' => fake()->words(2, true),
            'quantity' => 1,
            'unit_amount_cents' => fake()->numberBetween(1000, 50000),
        ];
    }

    public function forOrder(ServiceOrder $order): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $order->tenant_id,
            'service_order_id' => $order->getKey(),
        ]);
    }
}
