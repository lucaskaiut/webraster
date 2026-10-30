<?php

namespace Database\Seeders\Demo;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Client\Models\ClientOrderItem;
use App\Modules\Client\Services\ContractBodyRenderer;
use App\Modules\Driver\Models\Driver;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Subscription\Enums\SubscriptionStatus;
use App\Modules\Shared\Support\Document;
use App\Modules\Stock\Enums\EquipmentStockMovementType;
use App\Modules\Stock\Models\EquipmentStockMovement;
use App\Modules\Stock\Models\Stock;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Enums\AssignmentEventType;
use App\Modules\Vehicle\Enums\VehicleType;
use App\Modules\Vehicle\Models\EquipmentAssignmentEvent;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleShare;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Clientes (empresas com frota e pessoas físicas), veículos, equipamentos
 * instalados e em estoque, motoristas, usuários do portal, pedidos,
 * assinaturas e contratos.
 */
class DemoClientSeeder extends Seeder
{
    /**
     * Endereço-base de cada região usada pelas rotas.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const ADDRESSES = [
        'centro' => ['Rua XV de Novembro', 'Centro', 'Curitiba', '80020310'],
        'batel' => ['Avenida do Batel', 'Batel', 'Curitiba', '80420090'],
        'cic' => ['Rua Desembargador Cid Campelo', 'Cidade Industrial', 'Curitiba', '81450000'],
        'aeroporto' => ['Avenida Rocha Pombo', 'Águas Belas', 'São José dos Pinhais', '83010620'],
        'portao' => ['Rua Carlos Klemtz', 'Fazendinha', 'Curitiba', '81320000'],
        'boqueirao' => ['Rua Pastor Antônio Polito', 'Boqueirão', 'Curitiba', '81650000'],
        'santa_felicidade' => ['Avenida Manoel Ribas', 'Santa Felicidade', 'Curitiba', '82020000'],
        'bacacheri' => ['Avenida Erasto Gaertner', 'Bacacheri', 'Curitiba', '82515000'],
        'pinhais' => ['Avenida Camilo di Lellis', 'Centro', 'Pinhais', '83323000'],
        'colombo' => ['Rodovia da Uva', 'Roça Grande', 'Colombo', '83402000'],
        'araucaria' => ['Avenida das Araucárias', 'Thomaz Coelho', 'Araucária', '83707000'],
        'fazenda_rio_grande' => ['Avenida Brasil', 'Eucaliptos', 'Fazenda Rio Grande', '83820000'],
        'hauer' => ['Avenida Marechal Floriano Peixoto', 'Hauer', 'Curitiba', '81610000'],
        'jardim_botanico' => ['Rua Engenheiro Ostoja Roguski', 'Jardim Botânico', 'Curitiba', '80210390'],
        'rodoferroviaria' => ['Avenida Presidente Affonso Camargo', 'Jardim Botânico', 'Curitiba', '80060090'],
        'campo_largo' => ['Rua Xavier da Silva', 'Centro', 'Campo Largo', '83601000'],
        'agua_verde' => ['Avenida República Argentina', 'Água Verde', 'Curitiba', '80240210'],
        'cabral' => ['Avenida Paraná', 'Cabral', 'Curitiba', '80035000'],
    ];

    /**
     * Catálogo por tipo: enum, marcas/modelos, câmbio, velocidade máxima,
     * consumo (km/l), tanque (l), modelo do rastreador, protocolo e uso.
     */
    private const CATALOG = [
        'truck' => [VehicleType::TRUCK, [['Volvo', 'FH 540'], ['Scania', 'R 450'], ['Mercedes-Benz', 'Actros 2651'], ['Volkswagen', 'Constellation 24.280'], ['Iveco', 'Tector 240E28']], 'automated', 90, 2.8, 600, 'Suntech ST4315U', 'suntech', 'heavy'],
        'van' => [VehicleType::VAN, [['Renault', 'Master'], ['Fiat', 'Ducato'], ['Mercedes-Benz', 'Sprinter 416'], ['Peugeot', 'Boxer']], 'manual', 100, 9.5, 80, 'Teltonika FMB920', 'teltonika', 'fleet'],
        'car' => [VehicleType::CAR, [['Fiat', 'Argo'], ['Volkswagen', 'Polo'], ['Chevrolet', 'Onix'], ['Hyundai', 'HB20'], ['Toyota', 'Corolla'], ['Renault', 'Kwid'], ['Jeep', 'Compass'], ['Fiat', 'Mobi'], ['Honda', 'Civic'], ['Volkswagen', 'T-Cross']], null, 110, 12.5, 50, 'Teltonika FMB920', 'teltonika', 'fleet'],
        'moto' => [VehicleType::MOTORCYCLE, [['Honda', 'CG 160 Fan'], ['Yamaha', 'Fazer 250'], ['Honda', 'Biz 125'], ['Honda', 'CB 300F Twister'], ['Yamaha', 'Factor 150']], 'manual', 90, 35, 15, 'Concox GT06N', 'gt06', 'fleet'],
        'pickup' => [VehicleType::PICKUP, [['Toyota', 'Hilux'], ['Fiat', 'Strada'], ['Chevrolet', 'S10'], ['Volkswagen', 'Saveiro'], ['Ford', 'Ranger'], ['Fiat', 'Toro']], null, 110, 10, 75, 'Teltonika FMB920', 'teltonika', 'fleet'],
        'dump' => [VehicleType::DUMP_TRUCK, [['Volkswagen', '31.330 Basculante'], ['Mercedes-Benz', 'Atego 2730 Basculante']], 'manual', 80, 2.5, 300, 'Suntech ST4315U', 'suntech', 'heavy'],
        'backhoe' => [VehicleType::BACKHOE, [['JCB', '3CX'], ['Case', '580N']], 'manual', 30, 6, 160, 'Queclink GV300', 'gl200', 'site'],
        'tractor' => [VehicleType::TRACTOR, [['John Deere', '6110J'], ['Massey Ferguson', '4707'], ['New Holland', 'T7.245']], 'manual', 40, 5, 200, 'Queclink GV300', 'gl200', 'site'],
    ];

    private const COLORS = ['Branco', 'Branco', 'Branco', 'Prata', 'Preto', 'Cinza', 'Vermelho', 'Azul'];

    private const CARRIERS = ['Vivo', 'Claro', 'TIM', 'Arqia (M2M)'];

    private string $password;

    private ?User $installer = null;

    public function run(DemoContext $ctx, AlertConfigService $alerts, ContractBodyRenderer $renderer): void
    {
        $this->password = Hash::make(DemoContext::PASSWORD);

        foreach ($this->companies() as $blueprint) {
            $this->seedClient($ctx, $alerts, $renderer, $blueprint);
        }

        foreach ($this->people($ctx) as $blueprint) {
            $this->seedClient($ctx, $alerts, $renderer, $blueprint);
        }

        $this->seedVehicleShares($ctx);
        $this->seedStockEquipments($ctx);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function companies(): array
    {
        return [
            ['name' => 'Transportadora Pinheiro', 'legal' => 'Transportadora Pinheiro Logística Ltda.', 'domain' => 'pinheirolog', 'base' => 'cic', 'months' => 13, 'fleet' => ['truck' => 8, 'van' => 4], 'services' => ['fleet', 'telemetry'], 'due_day' => 5, 'profile' => 'active', 'managers' => ['Roberto Pinheiro', 'Sandra Moraes']],
            ['name' => 'Distribuidora Araucária', 'legal' => 'Distribuidora Araucária de Alimentos S.A.', 'domain' => 'distaraucaria', 'base' => 'araucaria', 'months' => 11, 'fleet' => ['truck' => 4, 'van' => 4], 'services' => ['fleet'], 'due_day' => 10, 'profile' => 'active', 'managers' => ['Marcos Vinícius Kowalski']],
            ['name' => 'Construtora Horizonte', 'legal' => 'Construtora Horizonte Paraná Ltda.', 'domain' => 'horizontepr', 'base' => 'portao', 'months' => 9, 'fleet' => ['pickup' => 4, 'dump' => 2, 'backhoe' => 1], 'services' => ['fleet'], 'due_day' => 15, 'profile' => 'active', 'managers' => ['Eng. Ricardo Tavares']],
            ['name' => 'Frigorífico Campos Gerais', 'legal' => 'Frigorífico Campos Gerais Ltda.', 'domain' => 'fcgerais', 'base' => 'campo_largo', 'months' => 8, 'fleet' => ['truck' => 5], 'services' => ['fleet', 'telemetry'], 'due_day' => 10, 'profile' => 'active', 'managers' => []],
            ['name' => 'Locadora Movimenta', 'legal' => 'Movimenta Locação de Veículos Ltda.', 'domain' => 'movimenta', 'base' => 'aeroporto', 'months' => 12, 'fleet' => ['car' => 10], 'services' => ['monthly_block'], 'due_day' => 20, 'profile' => 'active', 'managers' => ['Juliana Prado']],
            ['name' => 'Expresso Colombo', 'legal' => 'Expresso Colombo Encomendas Eireli', 'domain' => 'expressocolombo', 'base' => 'colombo', 'months' => 7, 'fleet' => ['van' => 3, 'moto' => 4], 'services' => ['fleet'], 'due_day' => 10, 'profile' => 'active', 'managers' => []],
            ['name' => 'Clínica Vida Plena', 'legal' => 'Clínica Vida Plena Serviços Médicos Ltda.', 'domain' => 'vidaplena', 'base' => 'agua_verde', 'months' => 6, 'fleet' => ['van' => 3], 'services' => ['fleet'], 'due_day' => 5, 'profile' => 'active', 'contract' => 'pending', 'managers' => []],
            ['name' => 'Agro Sul Máquinas', 'legal' => 'Agro Sul Máquinas e Implementos Ltda.', 'domain' => 'agrosul', 'base' => 'fazenda_rio_grande', 'months' => 5, 'fleet' => ['tractor' => 3, 'pickup' => 2], 'services' => ['fleet'], 'due_day' => 15, 'profile' => 'active', 'managers' => []],
            ['name' => 'Auto Escola Pinhais', 'legal' => 'Centro de Formação de Condutores Pinhais Ltda.', 'domain' => 'aepinhais', 'base' => 'pinhais', 'months' => 10, 'fleet' => ['car' => 4, 'moto' => 2], 'services' => ['monthly'], 'due_day' => 10, 'profile' => 'active', 'managers' => []],
            ['name' => 'Floricultura Jardim Botânico', 'legal' => 'Floricultura Jardim Botânico Ltda. ME', 'domain' => 'florjb', 'base' => 'jardim_botanico', 'months' => 4, 'fleet' => ['van' => 2], 'services' => ['monthly'], 'due_day' => 10, 'profile' => 'overdue', 'managers' => []],
            ['name' => 'Mudanças Rápidas Curitiba', 'legal' => 'Mudanças Rápidas Curitiba Transportes Ltda.', 'domain' => 'mudancasrapidas', 'base' => 'hauer', 'months' => 9, 'fleet' => ['truck' => 3], 'services' => ['fleet'], 'due_day' => 10, 'profile' => 'suspended', 'managers' => []],
            ['name' => 'TecServ Manutenção Industrial', 'legal' => 'TecServ Manutenção Industrial Ltda.', 'domain' => 'tecserv', 'base' => 'boqueirao', 'months' => 2, 'fleet' => ['pickup' => 4], 'services' => ['fleet'], 'due_day' => 25, 'profile' => 'active', 'pending_installs' => 2, 'managers' => []],
            ['name' => 'Eventos Bacacheri', 'legal' => 'Bacacheri Eventos e Locações Ltda.', 'domain' => 'eventosbacacheri', 'base' => 'bacacheri', 'months' => 12, 'fleet' => ['van' => 2], 'services' => ['fleet'], 'due_day' => 10, 'profile' => 'cancelled', 'managers' => []],
            ['name' => 'Rodo Express Cabral', 'legal' => 'Rodo Express Cabral Transportes Ltda.', 'domain' => 'rodoexpress', 'base' => 'cabral', 'months' => 0, 'fleet' => ['truck' => 2], 'services' => ['fleet', 'telemetry'], 'due_day' => 10, 'profile' => 'active', 'contract' => 'pending', 'pending_installs' => 2, 'managers' => []],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function people(DemoContext $ctx): array
    {
        $bases = array_keys(self::ADDRESSES);
        $plans = ['essential', 'essential', 'essential', 'block', 'block', 'family', 'annual'];
        $people = [];

        for ($i = 0; $i < 18; $i++) {
            $plan = $plans[$i % count($plans)];
            $fleet = match (true) {
                $plan === 'family' => ['car' => 2, 'moto' => 1],
                $i % 5 === 0 => ['moto' => 1],
                $i % 7 === 3 => ['pickup' => 1],
                default => ['car' => 1],
            };

            $people[] = [
                'name' => $ctx->faker->firstName().' '.$ctx->faker->lastName().' '.$ctx->faker->lastName(),
                'person' => true,
                'base' => $bases[$i % count($bases)],
                'months' => $ctx->faker->numberBetween(1, 13),
                'fleet' => $fleet,
                'plan' => $plan,
                'due_day' => $ctx->faker->randomElement([5, 10, 15, 20]),
                'profile' => match ($i) {
                    4, 11 => 'overdue',
                    16 => 'cancelled',
                    default => 'active',
                },
                'contract' => match ($i) {
                    2 => 'pending',
                    9 => 'waived',
                    default => 'signed',
                },
                'managers' => [],
            ];
        }

        return $people;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function seedClient(DemoContext $ctx, AlertConfigService $alerts, ContractBodyRenderer $renderer, array $blueprint): void
    {
        $faker = $ctx->faker;
        $person = (bool) ($blueprint['person'] ?? false);
        $base = $blueprint['base'];
        $profile = $blueprint['profile'];
        $since = $ctx->now->subMonths($blueprint['months'])->subDays($faker->numberBetween(2, 25))->setTime(12, 0)->addMinutes($faker->numberBetween(0, 480));
        [$street, $neighborhood, $city, $zip] = self::ADDRESSES[$base];
        $number = (string) $faker->numberBetween(30, 4800);
        $point = $ctx->near($ctx->place($base)['lat'], $ctx->place($base)['lng'], $person ? 1500 : 250);
        $domain = $person ? 'email.localhost' : "{$blueprint['domain']}.localhost";
        $email = $person ? $ctx->email($blueprint['name'], $domain) : "contato@{$domain}";
        $plan = $person ? $ctx->financePlans[$blueprint['plan']] : null;

        $client = Client::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'name' => $blueprint['name'],
            'legal_name' => $person ? null : $blueprint['legal'],
            'trade_name' => $person ? null : $blueprint['name'],
            'document' => $person ? Document::fakeCpf() : Document::fakeCnpj(),
            'state_registration' => $person ? null : $faker->numerify('##########'),
            'email' => $email,
            'financial_email' => $person ? null : "financeiro@{$domain}",
            'phone' => $person ? $ctx->mobile() : '41'.$faker->numerify('3###').$faker->numerify('####'),
            'street' => $street,
            'number' => $number,
            'complement' => $person ? $faker->optional(0.3)->randomElement(['Apto 302', 'Casa 2', 'Bloco B, apto 14']) : $faker->optional(0.5)->randomElement(['Galpão 3', 'Sala 1201', 'Barracão A']),
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => 'PR',
            'zip' => $zip,
            'latitude' => $point['lat'],
            'longitude' => $point['lng'],
            'geocoded_address' => "{$street}, {$number} - {$neighborhood}, {$city} - PR, {$zip}",
            'is_active' => $profile !== 'cancelled',
            'without_contract' => ($blueprint['contract'] ?? 'signed') === 'waived',
            'plan_id' => $plan?->getKey(),
            'created_at' => $since,
        ]);

        $users = [$this->seedClientUser($ctx, $client, $client->name, $email, true, $since)];

        foreach ($blueprint['managers'] as $manager) {
            $users[] = $this->seedClientUser($ctx, $client, $manager, $ctx->email($manager, $domain), false, $since->addDays(3));
        }

        $alerts->ensureClientDefaults($client, $ctx->tenant->getKey());

        $vehicles = $this->seedFleet($ctx, $alerts, $client, $blueprint, $since);

        if (! $person) {
            $this->seedDrivers($ctx, $client, $vehicles);
        }

        [$order, $subscription] = $person
            ? [null, $this->seedPlanSubscription($ctx, $client, $plan, $blueprint, $since)]
            : $this->seedOrder($ctx, $client, $vehicles, $blueprint, $since);

        $this->seedContract($ctx, $renderer, $client, $order, $blueprint['contract'] ?? 'signed', $since);

        $ctx->clients[] = [
            'client' => $client,
            'kind' => $person ? 'person' : 'company',
            'base' => $base,
            'since' => $since,
            'profile' => $profile,
            'address' => $client->geocoded_address,
            'subscription' => $subscription,
            'users' => $users,
        ];
    }

    private function seedClientUser(DemoContext $ctx, Client $client, string $name, string $email, bool $primary, CarbonImmutable $at): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'phone' => $ctx->mobile(),
            'client_id' => $client->getKey(),
            'is_primary' => $primary,
            'type' => UserType::CLIENT->value,
            'password' => $this->password,
        ]);
        $user->forceFill(['tenant_id' => $ctx->tenant->getKey(), 'created_at' => $at])->save();
        $user->assignRole($ctx->roles[DefaultRole::CLIENT->value]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return list<Vehicle>
     */
    private function seedFleet(DemoContext $ctx, AlertConfigService $alerts, Client $client, array $blueprint, CarbonImmutable $since): array
    {
        $faker = $ctx->faker;
        $profile = $blueprint['profile'];
        $pendingInstalls = (int) ($blueprint['pending_installs'] ?? 0);
        $total = array_sum($blueprint['fleet']);
        $index = 0;
        $vehicles = [];

        foreach ($blueprint['fleet'] as $type => $count) {
            [$vehicleType, $models, $transmission, $maxSpeed, $consumption, $tank, $deviceModel, $protocol, $usage] = self::CATALOG[$type];

            for ($i = 0; $i < $count; $i++) {
                $index++;
                [$brand, $model] = $faker->randomElement($models);
                $year = $faker->numberBetween($type === 'truck' ? 2014 : 2016, 2025);
                $installed = $index <= $total - $pendingInstalls;
                $installedAt = $installed
                    ? $since->addDays($faker->numberBetween(1, 12))->setTime($faker->numberBetween(11, 19), $faker->randomElement([0, 15, 30, 45]))
                    : null;

                if ($installedAt !== null && $installedAt->greaterThan($ctx->now->subDays(1))) {
                    $installedAt = $ctx->now->subDays(1)->setTime(10, 0);
                }

                $vehicle = Vehicle::query()->create([
                    'tenant_id' => $ctx->tenant->getKey(),
                    'client_id' => $client->getKey(),
                    'plate' => $ctx->plate(),
                    'chassis' => strtoupper($faker->bothify('9??#####??######')),
                    'renavam' => $faker->numerify('###########'),
                    'brand' => $brand,
                    'model' => $model,
                    'color' => $faker->randomElement(self::COLORS),
                    'year' => $year,
                    'vehicle_type' => $vehicleType->value,
                    'transmission' => $transmission ?? $faker->randomElement(['manual', 'automatic']),
                    'odometer' => $usage === 'site' ? $faker->numberBetween(800, 6000) : $faker->numberBetween(8_000, $type === 'truck' ? 650_000 : 140_000),
                    'max_speed_kmh' => $maxSpeed,
                    'average_consumption' => $consumption,
                    'tank_capacity' => $tank,
                    'fipe_brand' => in_array($type, ['car', 'pickup', 'moto'], true) ? $brand : null,
                    'fipe_model' => in_array($type, ['car', 'pickup', 'moto'], true) ? "{$model} {$faker->randomElement(['1.0', '1.6', '2.0', '2.8 TDI'])}" : null,
                    'fipe_model_year' => in_array($type, ['car', 'pickup', 'moto'], true) ? "{$year}" : null,
                    'fipe_fuel' => in_array($type, ['car', 'pickup', 'moto'], true) ? ($type === 'pickup' ? 'Diesel' : 'Flex') : null,
                    'fipe_value' => in_array($type, ['car', 'pickup', 'moto'], true) ? 'R$ '.number_format($faker->numberBetween($type === 'moto' ? 9_000 : 55_000, $type === 'pickup' ? 260_000 : 160_000), 2, ',', '.') : null,
                    'fipe_reference_month' => in_array($type, ['car', 'pickup', 'moto'], true) ? 'outubro de 2026' : null,
                    'is_active' => $profile !== 'cancelled',
                    'created_at' => $since,
                ]);

                $alerts->ensureVehicleDefaults($vehicle, $ctx->tenant->getKey());

                if (in_array($type, ['truck', 'dump', 'van'], true) || ($type === 'car' && ! ($blueprint['person'] ?? false))) {
                    $this->enableSpeedAlert($vehicle, $maxSpeed);
                }

                $equipment = null;

                if ($installed) {
                    $equipment = $this->seedInstalledEquipment($ctx, $vehicle, $deviceModel, $installedAt, $profile);
                }

                $ctx->fleet[] = [
                    'vehicle' => $vehicle,
                    'equipment' => $equipment,
                    'client' => $client,
                    'base' => $blueprint['base'],
                    'signal' => $this->signalProfile($ctx, $profile, $equipment),
                    'installed_at' => $installedAt,
                    'usage' => ($blueprint['person'] ?? false) ? 'personal' : $usage,
                    'protocol' => $protocol,
                    'installer' => $equipment !== null ? $this->installer : null,
                ];

                $vehicles[] = $vehicle;
            }
        }

        return $vehicles;
    }

    private function enableSpeedAlert(Vehicle $vehicle, int $limit): void
    {
        AlertConfig::query()
            ->withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('type', AlertType::SPEED->value)
            ->update([
                'is_enabled' => true,
                'settings' => json_encode(['speed_limit_kmh' => $limit, 'min_duration_seconds' => 30]),
            ]);
    }

    private function signalProfile(DemoContext $ctx, string $clientProfile, ?Equipment $equipment): string
    {
        if ($equipment === null) {
            return 'none';
        }

        if ($clientProfile === 'suspended') {
            return 'suspended';
        }

        $roll = $ctx->faker->numberBetween(1, 100);

        return match (true) {
            $roll <= 6 => 'offline_recent',
            $roll <= 10 => 'offline_day',
            $roll <= 14 => 'offline_long',
            default => 'online',
        };
    }

    private function seedInstalledEquipment(DemoContext $ctx, Vehicle $vehicle, string $model, CarbonImmutable $installedAt, string $profile): Equipment
    {
        $faker = $ctx->faker;
        $purchasedAt = $installedAt->subDays($faker->numberBetween(20, 60))->setTime(10, 0);
        $technician = $faker->randomElement($ctx->technicians);
        $technicianStock = $ctx->stocks["technician:{$technician->getKey()}"];
        $central = $ctx->stocks['central'];
        $removed = $profile === 'cancelled';
        $this->installer = $technician;

        $equipment = Equipment::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'vehicle_id' => $removed ? null : $vehicle->getKey(),
            'stock_id' => $removed ? $central->getKey() : null,
            'imei' => '86'.$faker->numerify('#############'),
            'model' => $model,
            'line_number' => '419'.$faker->numerify('########'),
            'carrier' => $faker->randomElement(self::CARRIERS),
            'is_active' => true,
            'created_at' => $purchasedAt,
        ]);

        $operator = $faker->randomElement($ctx->staff);

        $this->movement($ctx, $equipment, null, $central, EquipmentStockMovementType::ENTRY, 'Entrada por compra — NF '.$faker->numerify('######'), $operator, $purchasedAt);
        $this->movement($ctx, $equipment, $central, $technicianStock, EquipmentStockMovementType::TRANSFER, "Transferência para o técnico {$technician->name}", $operator, $installedAt->subDays($faker->numberBetween(1, 10)));
        $this->movement($ctx, $equipment, $technicianStock, null, EquipmentStockMovementType::INSTALLATION, "Instalado no veículo {$vehicle->plate}", $technician, $installedAt);

        EquipmentAssignmentEvent::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'equipment_id' => $equipment->getKey(),
            'event' => AssignmentEventType::INSTALLATION,
            'occurred_at' => $installedAt,
            'notes' => "Instalação realizada por {$technician->name}.",
        ]);

        if ($removed) {
            $removedAt = $ctx->now->subMonths(2)->setTime(14, 0);

            EquipmentAssignmentEvent::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'equipment_id' => $equipment->getKey(),
                'event' => AssignmentEventType::REMOVAL,
                'occurred_at' => $removedAt,
                'notes' => 'Retirada por encerramento de contrato.',
            ]);

            $this->movement($ctx, $equipment, null, $central, EquipmentStockMovementType::RETURN, "Retorno do veículo {$vehicle->plate} (contrato encerrado)", $technician, $removedAt);
        }

        return $equipment;
    }

    private function movement(
        DemoContext $ctx,
        Equipment $equipment,
        ?Stock $from,
        ?Stock $to,
        EquipmentStockMovementType $type,
        string $description,
        User $by,
        CarbonImmutable $at,
    ): void {
        EquipmentStockMovement::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'equipment_id' => $equipment->getKey(),
            'from_stock_id' => $from?->getKey(),
            'to_stock_id' => $to?->getKey(),
            'type' => $type,
            'description' => $description,
            'moved_by' => $by->getKey(),
            'occurred_at' => $at,
            'created_at' => $at,
        ]);
    }

    /**
     * @param  list<Vehicle>  $vehicles
     */
    private function seedDrivers(DemoContext $ctx, Client $client, array $vehicles): void
    {
        $faker = $ctx->faker;

        foreach ($vehicles as $index => $vehicle) {
            if ((int) $vehicle->vehicle_type === VehicleType::BACKHOE->value || $faker->numberBetween(1, 100) > 75) {
                continue;
            }

            $expires = match (true) {
                $index === 1 => $ctx->now->addDays(18),
                $index === 4 => $ctx->now->subDays(12),
                default => $ctx->now->addMonths($faker->numberBetween(4, 50)),
            };

            Driver::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'client_id' => $client->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'name' => $faker->firstNameMale().' '.$faker->lastName(),
                'document' => Document::fakeCpf(),
                'phone' => $ctx->mobile(),
                'email' => null,
                'cnh_number' => $faker->numerify('###########'),
                'cnh_expires_at' => $expires->toDateString(),
                'notes' => $faker->optional(0.2)->randomElement(['Motorista de rota fixa.', 'Cobre férias da equipe.', 'Habilitado para cargas perigosas (MOPP).']),
                'is_active' => true,
                'created_at' => $vehicle->created_at,
            ]);
        }

        // Motorista reserva, sem veículo vinculado.
        Driver::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'client_id' => $client->getKey(),
            'vehicle_id' => null,
            'name' => $faker->firstNameMale().' '.$faker->lastName(),
            'document' => Document::fakeCpf(),
            'phone' => $ctx->mobile(),
            'cnh_number' => $faker->numerify('###########'),
            'cnh_expires_at' => $ctx->now->addYears(2)->toDateString(),
            'notes' => 'Motorista reserva.',
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<Vehicle>  $vehicles
     * @param  array<string, mixed>  $blueprint
     * @return array{0: ClientOrder, 1: FinanceSubscription}
     */
    private function seedOrder(DemoContext $ctx, Client $client, array $vehicles, array $blueprint, CarbonImmutable $since): array
    {
        $heavy = array_values(array_filter(
            $vehicles,
            fn (Vehicle $vehicle) => in_array((int) $vehicle->vehicle_type, [VehicleType::TRUCK->value, VehicleType::DUMP_TRUCK->value], true),
        ));

        $items = [];

        foreach ($blueprint['services'] as $key) {
            $covered = $key === 'telemetry' ? $heavy : $vehicles;

            if ($covered === []) {
                continue;
            }

            $items[] = [$ctx->services[$key], $covered];
        }

        $total = array_sum(array_map(fn ($item) => $item[0]->amount_cents * count($item[1]), $items));

        $order = ClientOrder::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'client_id' => $client->getKey(),
            'due_day' => $blueprint['due_day'],
            'periodicity' => BillingPeriodicity::MONTHLY,
            'total_cents' => $total,
            'created_at' => $since,
        ]);

        foreach ($items as [$service, $covered]) {
            $item = ClientOrderItem::query()->create([
                'client_order_id' => $order->getKey(),
                'service_id' => $service->getKey(),
                'service_name' => $service->name,
                'unit_amount_cents' => $service->amount_cents,
                'quantity' => count($covered),
                'line_total_cents' => $service->amount_cents * count($covered),
            ]);

            $item->vehicles()->sync(array_map(fn (Vehicle $vehicle) => $vehicle->getKey(), $covered));
        }

        $subscription = $this->createSubscription(
            $ctx,
            $client,
            null,
            implode(' + ', array_map(fn ($item) => $item[0]->name, $items)),
            $total,
            BillingPeriodicity::MONTHLY,
            $blueprint,
            $since,
        );

        $order->forceFill(['finance_subscription_id' => $subscription->getKey()])->save();

        return [$order, $subscription];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function seedPlanSubscription(DemoContext $ctx, Client $client, FinancePlan $plan, array $blueprint, CarbonImmutable $since): FinanceSubscription
    {
        return $this->createSubscription($ctx, $client, $plan, $plan->name, $plan->amount_cents, $plan->periodicity, $blueprint, $since);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function createSubscription(
        DemoContext $ctx,
        Client $client,
        ?FinancePlan $plan,
        string $name,
        int $price,
        BillingPeriodicity $periodicity,
        array $blueprint,
        CarbonImmutable $since,
    ): FinanceSubscription {
        $status = match ($blueprint['profile']) {
            'overdue' => SubscriptionStatus::PAST_DUE,
            'suspended' => SubscriptionStatus::SUSPENDED,
            'cancelled' => SubscriptionStatus::CANCELLED,
            default => SubscriptionStatus::ACTIVE,
        };

        return FinanceSubscription::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'client_id' => $client->getKey(),
            'plan_id' => $plan?->getKey(),
            'plan_name' => $name,
            'plan_price_cents' => $price,
            'plan_periodicity' => $periodicity,
            'status' => $status,
            'due_day' => $blueprint['due_day'],
            'block_on_overdue' => true,
            'block_after_days' => 5,
            'started_at' => $since,
            'cancelled_at' => $status === SubscriptionStatus::CANCELLED ? $ctx->now->subMonths(2)->setTime(11, 0) : null,
            'created_at' => $since,
        ]);
    }

    private function seedContract(DemoContext $ctx, ContractBodyRenderer $renderer, Client $client, ?ClientOrder $order, string $state, CarbonImmutable $since): void
    {
        if ($state === 'waived') {
            ClientContract::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'client_id' => $client->getKey(),
                'body' => '',
                'signature_status' => ContractSignatureStatus::SIGNED,
                'signed_at' => $since,
                'created_at' => $since,
            ]);

            return;
        }

        $body = $renderer->render($ctx->contract, $client, $order?->load('items.service', 'items.vehicles'));
        $signed = $state === 'signed';
        $signedAt = $since->addDays($ctx->faker->numberBetween(0, 3))->setTime($ctx->faker->numberBetween(9, 19), $ctx->faker->numberBetween(0, 59));

        if ($signedAt->greaterThan($ctx->now)) {
            $signedAt = $ctx->now->subHours(3);
        }

        $digits = preg_replace('/\D+/', '', (string) $client->document);
        $signerCpf = strlen($digits) === 11 ? $digits : Document::fakeCpf();

        ClientContract::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'client_id' => $client->getKey(),
            'contract_id' => $ctx->contract->getKey(),
            'valid_until' => $signed ? $signedAt->addYear()->toDateString() : null,
            'signature_status' => $signed ? ContractSignatureStatus::SIGNED : ContractSignatureStatus::PENDING,
            'signed_at' => $signed ? $signedAt : null,
            'signer_name' => $signed ? (strlen($digits) === 11 ? $client->name : $ctx->faker->name()) : null,
            'signer_cpf' => $signed ? $signerCpf : null,
            'signer_birth_date' => $signed ? $ctx->faker->dateTimeBetween('-65 years', '-25 years')->format('Y-m-d') : null,
            'signed_ip' => $signed ? $ctx->faker->ipv4() : null,
            'signed_user_agent' => $signed ? 'RastroSul/2.4.1 (Android 14; SM-A546E)' : null,
            'document_hash' => $signed ? hash('sha256', $body) : null,
            'body' => $body,
            'document_version' => 1,
            'created_at' => $since,
        ]);
    }

    private function seedVehicleShares(DemoContext $ctx): void
    {
        $byName = collect($ctx->clients)->keyBy(fn ($entry) => $entry['client']->name);
        $owner = $byName->get('Construtora Horizonte');
        $partner = $byName->get('TecServ Manutenção Industrial');

        if ($owner === null || $partner === null) {
            return;
        }

        $vehicles = collect($ctx->fleet)
            ->filter(fn ($entry) => $entry['client']->is($owner['client']) && $entry['equipment'] !== null)
            ->take(2);

        foreach ($vehicles as $entry) {
            VehicleShare::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'vehicle_id' => $entry['vehicle']->getKey(),
                'client_id' => $partner['client']->getKey(),
                'created_by' => $ctx->admin->getKey(),
                'created_at' => $ctx->now->subDays(20),
            ]);
        }
    }

    /**
     * Equipamentos disponíveis: estoque central, filial e com os técnicos.
     */
    private function seedStockEquipments(DemoContext $ctx): void
    {
        $faker = $ctx->faker;
        $models = ['Teltonika FMB920', 'Teltonika FMB920', 'Concox GT06N', 'Suntech ST4315U', 'Queclink GV300', 'J16 4G'];
        $operator = $ctx->staff[1];

        $batches = [['central', 16], ['araucaria', 6]];

        foreach ($ctx->technicians as $technician) {
            $batches[] = ["technician:{$technician->getKey()}", 3];
        }

        foreach ($batches as [$stockKey, $count]) {
            $stock = $ctx->stocks[$stockKey];

            for ($i = 0; $i < $count; $i++) {
                $purchasedAt = $ctx->now->subDays($faker->numberBetween(5, 90))->setTime(10, 0);
                $withChip = $faker->numberBetween(1, 100) <= 80;

                $equipment = Equipment::query()->create([
                    'tenant_id' => $ctx->tenant->getKey(),
                    'vehicle_id' => null,
                    'stock_id' => $stock->getKey(),
                    'imei' => '86'.$faker->numerify('#############'),
                    'model' => $faker->randomElement($models),
                    'line_number' => $withChip ? '419'.$faker->numerify('########') : null,
                    'carrier' => $withChip ? $faker->randomElement(self::CARRIERS) : null,
                    'is_active' => true,
                    'created_at' => $purchasedAt,
                ]);

                $this->movement($ctx, $equipment, null, $ctx->stocks['central'], EquipmentStockMovementType::ENTRY, 'Entrada por compra — NF '.$faker->numerify('######'), $operator, $purchasedAt);

                if ($stockKey !== 'central') {
                    $this->movement($ctx, $equipment, $ctx->stocks['central'], $stock, EquipmentStockMovementType::TRANSFER, "Transferência para {$stock->name}", $operator, $purchasedAt->addDays($faker->numberBetween(1, 4)));
                }
            }
        }

        // Equipamentos com defeito, inativos no estoque central.
        foreach (['Concox GT06N', 'Teltonika FMB920'] as $model) {
            $equipment = Equipment::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'stock_id' => $ctx->stocks['central']->getKey(),
                'imei' => '86'.$faker->numerify('#############'),
                'model' => $model,
                'is_active' => false,
                'created_at' => $ctx->now->subMonths(6),
            ]);

            $this->movement($ctx, $equipment, null, $ctx->stocks['central'], EquipmentStockMovementType::RETURN, 'Retorno para análise — equipamento sem comunicação (RMA).', $operator, $ctx->now->subDays(40));
        }
    }
}
