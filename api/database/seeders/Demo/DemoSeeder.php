<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;

/**
 * Base completa para demonstração: umbrella Nuvemax + tenant filho
 * "Rastro Sul Rastreamento" com clientes, frota, rastreamento, ordens de
 * serviço, financeiro e CRM.
 *
 * Todos os usuários usam a senha "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $ctx = new DemoContext;

        foreach ($this->steps() as $step) {
            $this->callWith($step, ['ctx' => $ctx]);
        }
    }

    /**
     * @return list<class-string<Seeder>>
     */
    private function steps(): array
    {
        return [
            DemoTenantSeeder::class,
            DemoCatalogSeeder::class,
            DemoClientSeeder::class,
            DemoFinanceSeeder::class,
            DemoServiceOrderSeeder::class,
            DemoTrackingSeeder::class,
            DemoCrmSeeder::class,
        ];
    }
}
