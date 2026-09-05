<?php

namespace Database\Factories;

use App\Modules\Client\Models\Client;
use App\Modules\Poi\Models\Poi;
use App\Modules\Poi\Models\PoiCategory;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poi>
 */
class PoiFactory extends Factory
{
    protected $model = Poi::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'poi_category_id' => PoiCategory::factory(),
            'name' => 'POI '.fake()->unique()->company(),
            'description' => fake()->optional()->sentence(),
            'latitude' => (float) fake()->latitude(-30, -20),
            'longitude' => (float) fake()->longitude(-55, -45),
            'address' => fake()->optional()->streetAddress(),
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

    public function forCategory(PoiCategory $category): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $category->tenant_id,
            'poi_category_id' => $category->getKey(),
        ]);
    }
}
