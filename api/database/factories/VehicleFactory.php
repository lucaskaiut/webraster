<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $letters = strtoupper(fake()->lexify('???'));
        $numbers = fake()->numerify('####');

        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'plate' => $letters.$numbers,
            'chassis' => strtoupper(fake()->bothify('###########??????')),
            'renavam' => fake()->numerify('###########'),
            'brand' => fake()->randomElement(['Volkswagen', 'Fiat', 'Chevrolet', 'Toyota', 'Ford']),
            'model' => fake()->randomElement(['Gol', 'Uno', 'Onix', 'Corolla', 'Ka']),
            'color' => fake()->randomElement(['Branco', 'Prata', 'Preto', 'Vermelho', 'Azul']),
            'year' => (int) fake()->numberBetween(2010, (int) date('Y')),
            'transmission' => fake()->randomElement(['manual', 'automatic', 'automated']),
            'odometer' => (int) fake()->numberBetween(0, 250000),
            'average_consumption' => fake()->randomFloat(2, 8, 18),
            'tank_capacity' => fake()->randomFloat(2, 40, 80),
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
