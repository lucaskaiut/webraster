<?php

namespace Database\Factories;

use App\Modules\Checklist\Enums\ChecklistType;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checklist>
 */
class ChecklistFactory extends Factory
{
    protected $model = Checklist::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement([ChecklistType::INITIAL, ChecklistType::FINAL]),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function initial(): static
    {
        return $this->state(fn (): array => ['type' => ChecklistType::INITIAL]);
    }

    public function final(): static
    {
        return $this->state(fn (): array => ['type' => ChecklistType::FINAL]);
    }
}
