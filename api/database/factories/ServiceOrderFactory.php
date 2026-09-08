<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Enums\ServiceOrderType;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    protected $model = ServiceOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'type' => ServiceOrderType::INSTALLATION,
            'status' => ServiceOrderStatus::OPEN,
            'priority' => ServiceOrderPriority::NORMAL,
            'client_id' => Client::factory(),
            'description' => fake()->sentence(),
            'notes' => null,
            'scheduled_start_at' => now()->addDay()->setTime(9, 0),
            'scheduled_end_at' => now()->addDay()->setTime(11, 0),
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->getKey(),
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => ServiceOrderStatus::OPEN]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => ServiceOrderStatus::IN_PROGRESS]);
    }
}
