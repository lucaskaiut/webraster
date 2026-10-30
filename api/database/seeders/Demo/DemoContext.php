<?php

namespace Database\Seeders\Demo;

use App\Modules\ACL\Models\Role;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Client\Models\Client;
use App\Modules\Contract\Models\Contract;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Poi\Models\PoiCategory;
use App\Modules\Service\Models\Service;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Str;

/**
 * Estado compartilhado entre as etapas do seed de demonstração.
 *
 * As etapas rodam em sequência e cada uma lê o que as anteriores criaram
 * (tenant, equipe, catálogo, clientes e frota).
 */
final class DemoContext
{
    public const SEED = 20261029;

    public const PASSWORD = 'password';

    /** Fuso da operação; o banco guarda tudo em UTC. */
    public const TIMEZONE = 'America/Sao_Paulo';

    public CarbonImmutable $now;

    public Generator $faker;

    public Tenant $umbrella;

    public Tenant $tenant;

    public User $admin;

    /** @var array<string, Role> */
    public array $roles = [];

    /** @var list<User> */
    public array $staff = [];

    /** @var list<User> */
    public array $technicians = [];

    /** @var array<string, Stock> */
    public array $stocks = [];

    /** @var array<string, Service> */
    public array $services = [];

    /** @var array<string, FinancePlan> */
    public array $financePlans = [];

    public Contract $contract;

    /** @var array<string, Checklist> */
    public array $checklists = [];

    /** @var array<string, PoiCategory> */
    public array $poiCategories = [];

    /**
     * @var list<array{
     *     client: Client,
     *     kind: string,
     *     base: string,
     *     since: CarbonImmutable,
     *     profile: string,
     *     address: string,
     *     subscription: ?FinanceSubscription,
     *     users: list<User>,
     * }>
     */
    public array $clients = [];

    /**
     * Veículos com o cliente, a base de operação e o perfil de comunicação
     * (online, offline_recent, offline_day, offline_long, suspended).
     *
     * @var list<array{
     *     vehicle: Vehicle,
     *     equipment: ?Equipment,
     *     client: Client,
     *     base: string,
     *     signal: string,
     *     installed_at: ?CarbonImmutable,
     *     usage: string,
     *     protocol: string,
     *     installer: ?User,
     * }>
     */
    public array $fleet = [];

    /** @var array<string, array{lat: float, lng: float}> */
    public array $places = [];

    /** @var list<array{from: string, to: string, distance_m: int, duration_s: int, points: list<array{0: float, 1: float}>}> */
    public array $routes = [];

    private int $billingNumber = 0;

    public function __construct()
    {
        $this->now = CarbonImmutable::now()->startOfMinute();
        $this->faker = FakerFactory::create('pt_BR');
        $this->faker->seed(self::SEED);

        $data = json_decode((string) file_get_contents(__DIR__.'/../data/demo_routes.json'), true);
        $this->places = $data['places'];
        $this->routes = $data['routes'];
    }

    public function localNow(): CarbonImmutable
    {
        return $this->now->setTimezone(self::TIMEZONE);
    }

    public function nextBillingNumber(): int
    {
        return ++$this->billingNumber;
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public function place(string $key): array
    {
        return $this->places[$key];
    }

    /**
     * Ponto aleatório em até $meters metros do ponto informado.
     *
     * @return array{lat: float, lng: float}
     */
    public function near(float $lat, float $lng, int $meters): array
    {
        $distance = $this->faker->numberBetween(0, $meters);
        $angle = deg2rad($this->faker->numberBetween(0, 359));

        return [
            'lat' => round($lat + ($distance * cos($angle)) / 111_320, 7),
            'lng' => round($lng + ($distance * sin($angle)) / (111_320 * cos(deg2rad($lat))), 7),
        ];
    }

    /**
     * Trajetos que partem da base informada (rotas cadastradas nos dois
     * sentidos; as de volta são invertidas).
     *
     * @return list<array{to: string, distance_m: int, duration_s: int, points: list<array{0: float, 1: float}>}>
     */
    public function routesFrom(string $place): array
    {
        $routes = [];

        foreach ($this->routes as $route) {
            if ($route['from'] === $place) {
                $routes[] = [
                    'to' => $route['to'],
                    'distance_m' => $route['distance_m'],
                    'duration_s' => $route['duration_s'],
                    'points' => $route['points'],
                ];
            } elseif ($route['to'] === $place) {
                $routes[] = [
                    'to' => $route['from'],
                    'distance_m' => $route['distance_m'],
                    'duration_s' => $route['duration_s'],
                    'points' => array_reverse($route['points']),
                ];
            }
        }

        return $routes;
    }

    public function plate(): string
    {
        return strtoupper($this->faker->lexify('???')).$this->faker->numberBetween(0, 9)
            .strtoupper($this->faker->randomLetter()).$this->faker->numerify('##');
    }

    public function mobile(): string
    {
        return '419'.$this->faker->numerify('########');
    }

    public function email(string $name, string $domain): string
    {
        $slug = str_replace('-', '.', Str::slug($name));

        return "{$slug}@{$domain}";
    }
}
