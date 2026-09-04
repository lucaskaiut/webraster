<?php

namespace Database\Factories;

use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->company(),
            'document' => Document::fakeCnpj(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('419########'),
            'domain' => fake()->unique()->domainName(),
        ];
    }

    public function childOf(Tenant $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->getKey(),
        ]);
    }
}
