<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'document' => Document::fakeCpf(),
            'phone' => fake()->numerify('419########'),
            'email' => fake()->unique()->safeEmail(),
            'cnh_number' => fake()->numerify('###########'),
            'cnh_expires_at' => fake()->dateTimeBetween('+1 month', '+5 years')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->getKey(),
        ]);
    }
}
