<?php

namespace Database\Factories;

use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'token_hash' => ApiToken::hash(ApiToken::PREFIX.Str::random(48)),
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }
}
