<?php

namespace Database\Seeders;

use App\Modules\Tenant\Models\Tenant;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Tenant::query()->exists()) {
            $this->command?->warn('Banco já possui tenants; seed ignorado. Use "php artisan migrate:fresh --seed" para recriar a base de demonstração.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
