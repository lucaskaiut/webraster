<?php

namespace Database\Factories;

use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderPhoto;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderPhoto>
 */
class ServiceOrderPhotoFactory extends Factory
{
    protected $model = ServiceOrderPhoto::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'service_order_id' => ServiceOrder::factory(),
            'path' => 'uploads/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
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
