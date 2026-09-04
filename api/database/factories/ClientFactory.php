<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'document' => Document::fakeCnpj(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('419########'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->numberBetween(1, 9999),
            'complement' => null,
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['PR', 'SP', 'SC', 'RS', 'RJ']),
            'zip' => fake()->numerify('########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
