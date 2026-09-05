<?php

namespace Database\Factories;

use App\Modules\Poi\Models\PoiCategory;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PoiCategory>
 */
class PoiCategoryFactory extends Factory
{
    protected $model = PoiCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'color' => '#0f766e',
            'is_active' => true,
            'sort_order' => 10,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }
}
