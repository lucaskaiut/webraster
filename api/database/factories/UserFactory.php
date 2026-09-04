<?php

namespace Database\Factories;

use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('419########'),
            'document' => Document::fakeCpf(),
            'password' => static::$password ??= Hash::make('password'),
            'is_master' => false,
        ];
    }

    public function master(): static
    {
        return $this->state(fn (): array => [
            'is_master' => true,
        ]);
    }
}
