<?php

namespace Database\Seeders;

use App\Modules\Auth\DTOs\NewTenantData;
use App\Modules\Auth\DTOs\NewUserData;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Billing\Enums\RecurrenceUnit;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Tenant::query()->exists()) {
            return;
        }

        $result = app(AuthService::class)->register(
            new NewTenantData(
                name: 'Demo',
                document: '11222333000181',
                email: 'contato@demo.localhost',
                phone: '41999999999',
                domain: 'demo.localhost',
            ),
            new NewUserData(
                name: 'Administrador',
                email: 'admin@demo.localhost',
                phone: '41999999999',
                document: '52998224725',
                password: 'password',
            ),
        );

        $this->seedPlans($result->tenant);
    }

    private function seedPlans(Tenant $tenant): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Ideal para começar com o essencial.',
                'price' => '49.90',
                'recurrence_value' => 1,
                'recurrence_unit' => RecurrenceUnit::MONTHS,
                'free_trial_days' => 7,
                'active' => true,
            ],
            [
                'name' => 'Pro',
                'description' => 'Para times em crescimento com mais volume.',
                'price' => '99.90',
                'recurrence_value' => 1,
                'recurrence_unit' => RecurrenceUnit::MONTHS,
                'free_trial_days' => 7,
                'active' => true,
            ],
            [
                'name' => 'Business',
                'description' => 'Recursos avançados para operação em escala.',
                'price' => '199.90',
                'recurrence_value' => 1,
                'recurrence_unit' => RecurrenceUnit::MONTHS,
                'free_trial_days' => 0,
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->create([
                ...$plan,
                'tenant_id' => $tenant->getKey(),
            ]);
        }
    }
}
