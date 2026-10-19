<?php

namespace Tests\Feature\Scheduling;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Scheduling\Services\Geocoder;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const TUESDAY = '2026-10-20';

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, 06:00 em São Paulo.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 06:00:00', 'America/Sao_Paulo'));
    }

    public function test_manages_technician_data_in_user_registration(): void
    {
        [, $tenant] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($tenant));

        $operatorRoleId = $this->roleFor($tenant, DefaultRole::OPERATOR)->getKey();

        $id = $this->postJson('/api/users', [
            'name' => 'Técnico de Campo',
            'email' => 'tecnico@empresa.com',
            'password' => '12345678',
            'type' => UserType::TECHNICIAN->value,
            'role_ids' => [$operatorRoleId],
            'zip' => '80020-310',
            'street' => 'Rua XV de Novembro',
            'number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Curitiba',
            'state' => 'PR',
            'technician_active' => true,
            'work_hours' => [
                ['weekday' => 2, 'starts_at' => '08:00', 'ends_at' => '12:00'],
                ['weekday' => 2, 'starts_at' => '13:00', 'ends_at' => '18:00'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', UserType::TECHNICIAN->value)
            ->assertJsonPath('data.city', 'Curitiba')
            ->assertJsonCount(2, 'data.work_hours')
            ->assertJsonPath('data.work_hours.1.starts_at', '13:00')
            ->json('data.id');

        $user = User::query()->where('uuid', $id)->firstOrFail();

        $this->assertTrue($user->hasPermission(Permission::SERVICE_ORDER_EXECUTE));

        $this->putJson("/api/users/{$id}", [
            'work_hours' => [
                ['weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00'],
                ['weekday' => 1, 'starts_at' => '11:00', 'ends_at' => '14:00'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('work_hours');

        $this->getJson('/api/users?type=technician')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->uuid);
    }

    public function test_estimate_uses_service_duration_travel_and_suggests_free_slots(): void
    {
        $this->fakeGeoServices();
        [$tenant, $client, $technician, $service] = $this->schedulingScenario();

        ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $client->getKey(),
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => $this->localTime('08:30'),
            'scheduled_end_at' => $this->localTime('09:00'),
            'latitude' => -25.4284,
            'longitude' => -49.2733,
            'estimated_travel_minutes' => 30,
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson('/api/scheduling/estimate', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'date' => self::TUESDAY,
            'items' => [['service_id' => $service->uuid, 'quantity' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', 90)
            ->assertJsonPath('data.duration_source', 'services')
            ->assertJsonPath('data.travel.minutes', 0)
            ->assertJsonPath('data.travel.source', 'estimate')
            ->assertJsonPath('data.travel_return.minutes', 45)
            ->assertJsonPath('data.travel_return.distance_meters', 17000)
            ->assertJsonPath('data.travel_return.source', 'osrm')
            ->assertJsonPath('data.travel_round_trip.minutes', 45)
            ->assertJsonPath('data.travel_round_trip.distance_meters', 17000)
            ->assertJsonPath('data.travel_origin_kind', 'order')
            ->assertJsonPath('data.route.origin.latitude', -25.4284)
            ->assertJsonPath('data.route.destination.latitude', -25.4284)
            ->assertJsonCount(1, 'data.route.outbound')
            ->assertJsonCount(3, 'data.route.return');

        $slots = collect($response->json('data.slots'));

        // Jornada 08:00–13:00 com OS 08:30–09:00 no mesmo endereço:
        // a partir das 09:00 o novo atendimento já cabe; por ser o último do
        // dia, a volta à base não reduz o fim da jornada.
        $this->assertSame(
            [
                $this->localTime('09:00'),
                $this->localTime('09:15'),
                $this->localTime('09:30'),
                $this->localTime('09:45'),
                $this->localTime('10:00'),
                $this->localTime('10:15'),
                $this->localTime('10:30'),
                $this->localTime('10:45'),
                $this->localTime('11:00'),
                $this->localTime('11:15'),
                $this->localTime('11:30'),
            ],
            $slots->where('available', true)->pluck('start')->all(),
        );

        $blocked = $slots->where('available', false);
        $firstBlocked = $blocked->first();

        $this->assertNotEmpty($blocked);
        $this->assertStringContainsString('Conflito com', (string) $firstBlocked['reason']);
        $this->assertNotNull($firstBlocked['blocked_by']['code']);
        $this->assertSame($this->localTime('08:30'), $firstBlocked['blocked_by']['service_start']);
        $this->assertSame($this->localTime('09:00'), $firstBlocked['blocked_by']['service_end']);
        $this->assertSame($this->localTime('08:00'), $firstBlocked['blocked_by']['blocked_start']);
        $this->assertSame($this->localTime('09:30'), $firstBlocked['blocked_by']['blocked_end']);
    }

    public function test_rounds_duration_and_travel_to_fifteen_minute_grid(): void
    {
        $this->fakeGeoServices(outboundSeconds: 840, returnSeconds: 1080);
        [$tenant, $client, $technician, $service] = $this->schedulingScenario();
        $service->update(['estimated_minutes' => 14]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson('/api/scheduling/estimate', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'date' => self::TUESDAY,
            'items' => [['service_id' => $service->uuid, 'quantity' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', 15)
            ->assertJsonPath('data.travel.minutes', 15)
            ->assertJsonPath('data.travel_return.minutes', 30)
            ->assertJsonPath('data.travel_round_trip.minutes', 45);

        $slots = collect($response->json('data.slots'));

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $this->assertTrue($slot['available']);
            $start = CarbonImmutable::parse($slot['start'])->setTimezone('America/Sao_Paulo');
            $this->assertSame(0, $start->minute % 15);
        }
    }

    public function test_rounds_service_order_times_to_fifteen_minute_grid(): void
    {
        $this->fakeGeoServices();
        [$tenant, $client, $technician, $service] = $this->schedulingScenario();
        $service->update(['estimated_minutes' => 14]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('10:07'),
            'items' => [['service_id' => $service->uuid, 'quantity' => 1, 'unit_amount_cents' => 10000]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.scheduled_start_at', $this->localTime('10:15'))
            ->assertJsonPath('data.scheduled_end_at', $this->localTime('10:30'))
            ->assertJsonPath('data.estimated_duration_minutes', 15)
            ->assertJsonPath('data.estimated_travel_minutes', 30)
            ->assertJsonPath('data.estimated_return_travel_minutes', 45);
    }

    public function test_chains_travel_from_previous_order_and_persists_legs(): void
    {
        $a = ['latitude' => -25.4284, 'longitude' => -49.2733];
        $b = ['latitude' => -25.4500, 'longitude' => -49.3100];

        $this->fakeGeoServices(routeResolver: function (string $url) {
            // A → B (30 min) e B → base (30 min) usam tempos diferentes da
            // base → B (60 min), provando que a origem é o atendimento anterior.
            if (str_contains($url, '-49.273300,-25.428400;-49.310000,-25.450000')) {
                return $this->routeResponse(1800, 20000, [
                    [-49.2733, -25.4284],
                    [-49.3100, -25.4500],
                ]);
            }

            if (str_contains($url, '-49.310000,-25.450000;-49.300000,-25.400000')) {
                return $this->routeResponse(1800, 20000, [
                    [-49.3100, -25.4500],
                    [-49.3000, -25.4000],
                ]);
            }

            if (str_contains($url, '-49.300000,-25.400000;-49.310000,-25.450000')) {
                return $this->routeResponse(3600, 35000, [
                    [-49.3000, -25.4000],
                    [-49.3100, -25.4500],
                ]);
            }

            return null;
        });

        [$tenant, $clientA, $technician, $service] = $this->schedulingScenario();
        $service->update(['estimated_minutes' => 90]);

        $clientB = Client::factory()->for($tenant)->create([
            'street' => 'Rua B',
            'number' => '200',
            'neighborhood' => 'Centro',
            'city' => 'Curitiba',
            'state' => 'PR',
            'zip' => '80000-000',
        ]);

        $clientB->forceFill([
            'latitude' => $b['latitude'],
            'longitude' => $b['longitude'],
            'geocoded_address' => Geocoder::formatAddress($clientB->only([
                'street', 'number', 'neighborhood', 'city', 'state', 'zip',
            ])),
        ])->save();

        $existing = ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $clientA->getKey(),
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => $this->localTime('09:30'),
            'scheduled_end_at' => $this->localTime('10:00'),
            'address' => 'Endereço A',
            'latitude' => $a['latitude'],
            'longitude' => $a['longitude'],
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $response = $this->postJson('/api/scheduling/estimate', [
            'client_id' => $clientB->uuid,
            'technician_id' => $technician->uuid,
            'date' => self::TUESDAY,
            'items' => [['service_id' => $service->uuid, 'quantity' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('data.travel.minutes', 30)
            ->assertJsonPath('data.travel_origin_kind', 'order')
            ->assertJsonPath('data.travel_origin_order_code', $existing->code)
            ->assertJsonPath('data.travel_return.minutes', 30)
            ->assertJsonPath('data.travel_return_kind', 'base');

        $available = collect($response->json('data.slots'))->where('available', true);

        $this->assertSame($this->localTime('10:30'), $available->first()['start']);

        // Ao pré-visualizar um horário antes da OS existente, a volta é para ela.
        $this->postJson('/api/scheduling/estimate', [
            'client_id' => $clientB->uuid,
            'technician_id' => $technician->uuid,
            'date' => self::TUESDAY,
            'scheduled_start_at' => $this->localTime('08:00'),
            'items' => [['service_id' => $service->uuid, 'quantity' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('data.travel_origin_kind', 'base')
            ->assertJsonPath('data.travel_return_kind', 'order')
            ->assertJsonPath('data.travel_return_order_code', $existing->code);

        $created = $this->postJson('/api/service-orders', [
            'client_id' => $clientB->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('10:30'),
            'estimated_duration_minutes' => 90,
        ])
            ->assertCreated()
            ->json('data');

        $new = ServiceOrder::query()->where('uuid', $created['id'])->firstOrFail();
        $existing->refresh();

        $this->assertSame($existing->getKey(), $new->travel_from_order_id);
        $this->assertSame($new->getKey(), $existing->travel_to_order_id);
        $this->assertNull($new->travel_to_order_id);
        $this->assertSame(30, $new->estimated_travel_minutes);
        $this->assertSame(30, $new->estimated_return_travel_minutes);
        $this->assertSame(30, $existing->estimated_return_travel_minutes);
    }

    public function test_technician_dashboard_counts_categories_and_ignores_other_days(): void
    {
        [$tenant, $client, $technician] = $this->schedulingScenario();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        $at = fn (string $time) => CarbonImmutable::parse(
            '2026-10-19 '.$time,
            'America/Sao_Paulo',
        )->utc();

        $make = fn (string $status, string $start, string $end) => ServiceOrder::factory()
            ->for($tenant)
            ->create([
                'client_id' => $client->getKey(),
                'technician_id' => $technician->getKey(),
                'status' => $status,
                'scheduled_start_at' => $at($start),
                'scheduled_end_at' => $at($end),
            ]);

        $make(ServiceOrderStatus::OPEN->value, '11:00', '12:00');
        $make(ServiceOrderStatus::IN_PROGRESS->value, '10:00', '11:00');
        $make(ServiceOrderStatus::COMPLETED->value, '08:00', '09:00');
        $make(ServiceOrderStatus::CANCELLED->value, '09:00', '10:00');
        $make(ServiceOrderStatus::OPEN->value, '04:00', '05:00');
        $make(ServiceOrderStatus::IN_PROGRESS->value, '05:00', '05:30');

        $make(ServiceOrderStatus::OPEN->value, '10:00', '11:00');
        ServiceOrder::query()->latest('id')->first()?->forceFill([
            'scheduled_start_at' => $at('10:00')->addDay(),
            'scheduled_end_at' => $at('11:00')->addDay(),
        ])->save();

        Sanctum::actingAs($technician);

        $this->getJson('/api/technician/service-orders/dashboard?from=2026-10-19&to=2026-10-19')
            ->assertOk()
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.counts.open', 1)
            ->assertJsonPath('data.counts.in_progress', 1)
            ->assertJsonPath('data.counts.completed', 1)
            ->assertJsonPath('data.counts.cancelled', 1)
            ->assertJsonPath('data.counts.overdue', 2)
            ->assertJsonCount(6, 'data.orders');
    }

    public function test_technician_day_map_returns_orders_and_route_between_them(): void
    {
        $this->fakeGeoServices(routeResolver: function (string $url) {
            if (str_contains($url, '-49.273300,-25.428400;-49.310000,-25.450000')) {
                return $this->routeResponse(1800, 20000, [
                    [-49.2733, -25.4284],
                    [-49.3100, -25.4500],
                ]);
            }

            if (str_contains($url, '-49.310000,-25.450000;-49.300000,-25.400000')) {
                return $this->routeResponse(1800, 20000, [
                    [-49.3100, -25.4500],
                    [-49.3000, -25.4000],
                ]);
            }

            return null;
        });

        [$tenant, $clientA, $technician] = $this->schedulingScenario();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        $clientB = Client::factory()->for($tenant)->create(['name' => 'Cliente B']);

        ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $clientA->getKey(),
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => $this->localTime('09:00'),
            'scheduled_end_at' => $this->localTime('10:00'),
            'address' => 'Endereço A',
            'latitude' => -25.4284,
            'longitude' => -49.2733,
        ]);

        ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $clientB->getKey(),
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => $this->localTime('11:00'),
            'scheduled_end_at' => $this->localTime('12:00'),
            'address' => 'Endereço B',
            'latitude' => -25.4500,
            'longitude' => -49.3100,
        ]);

        Sanctum::actingAs($technician);

        $response = $this->getJson('/api/technician/service-orders/day?date='.self::TUESDAY)
            ->assertOk()
            ->assertJsonCount(2, 'data.stops')
            ->assertJsonCount(3, 'data.legs')
            ->assertJsonPath('data.total_minutes', 90)
            ->assertJsonPath('data.stops.0.address', 'Endereço A')
            ->assertJsonPath('data.stops.1.address', 'Endereço B');

        // O trecho do meio liga a OS A à OS B.
        $middleLeg = $response->json('data.legs.1');
        $this->assertSame('Endereço A', $middleLeg['from']['address']);
        $this->assertSame('Endereço B', $middleLeg['to']['address']);
        $this->assertSame(30, $middleLeg['minutes']);
        $this->assertCount(2, $middleLeg['geometry']);
    }

    public function test_creates_order_with_estimates_and_blocks_conflicts_and_work_hours(): void
    {
        $this->fakeGeoServices();
        [$tenant, $client, $technician, $service] = $this->schedulingScenario();

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('10:00'),
            'items' => [['service_id' => $service->uuid, 'quantity' => 1, 'unit_amount_cents' => 10000]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.address', 'Rua XV de Novembro, 100 - Centro - Curitiba/PR - 80020-310')
            ->assertJsonPath('data.estimated_duration_minutes', 90)
            ->assertJsonPath('data.estimated_travel_minutes', 30)
            ->assertJsonPath('data.travel_distance_meters', 15000)
            ->assertJsonPath('data.estimated_return_travel_minutes', 45)
            ->assertJsonPath('data.return_travel_distance_meters', 17000)
            ->assertJsonPath('data.scheduled_end_at', $this->localTime('11:30'));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('11:00'),
            'estimated_duration_minutes' => 10,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_start_at');

        $outside = $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('14:00'),
        ])->assertUnprocessable();

        $this->assertStringContainsString('jornada', $outside->json('errors.scheduled_start_at.0'));

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('14:00'),
            'ignore_schedule_conflict' => true,
        ])->assertCreated();
    }

    public function test_reminder_is_sent_once_and_reset_when_rescheduled(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $technician = User::factory()->for($tenant)->technician()->create();

        $order = ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $client->getKey(),
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => now()->addMinutes(20),
            'scheduled_end_at' => now()->addMinutes(80),
        ]);

        $this->artisan('service-orders:send-reminders')->assertSuccessful();
        $this->artisan('service-orders:send-reminders')->assertSuccessful();

        $this->assertSame(1, $this->notificationCount($technician, 'service_order.reminder'));
        $this->assertNotNull($order->fresh()->reminder_sent_at);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->putJson("/api/service-orders/{$order->uuid}", [
            'scheduled_start_at' => now()->addHours(3)->toIso8601String(),
            'scheduled_end_at' => now()->addHours(4)->toIso8601String(),
            'ignore_schedule_conflict' => true,
        ])->assertOk();

        $this->assertNull($order->fresh()->reminder_sent_at);
        $this->assertSame(1, $this->notificationCount($technician, 'service_order.rescheduled'));
    }

    public function test_pdf_and_attachments(): void
    {
        Storage::fake('local');
        [, $tenant] = $this->createOperationalChild();
        $order = ServiceOrder::factory()->for($tenant)->create([
            'client_id' => Client::factory()->for($tenant)->create()->getKey(),
        ]);

        Sanctum::actingAs($this->createAdmin($tenant));

        $this->get("/api/service-orders/{$order->uuid}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $attachmentId = $this->post("/api/service-orders/{$order->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'manual.pdf')
            ->json('data.id');

        $this->post("/api/service-orders/{$order->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('virus.exe', 10),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->getJson("/api/service-orders/{$order->uuid}")
            ->assertJsonCount(1, 'data.attachments');

        $this->get("/api/service-orders/{$order->uuid}/attachments/{$attachmentId}")->assertOk();

        $this->deleteJson("/api/service-orders/{$order->uuid}/attachments/{$attachmentId}")->assertOk();
        $this->assertDatabaseCount('service_order_attachments', 0);
    }

    public function test_technician_endpoints_only_expose_own_orders(): void
    {
        Storage::fake('local');
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create(['phone' => '41999990000']);
        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));
        $other = User::factory()->for($tenant)->create();

        $own = ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $client->getKey(),
            'technician_id' => $technician->getKey(),
            'scheduled_start_at' => now()->addDay(),
        ]);
        $foreign = ServiceOrder::factory()->for($tenant)->create([
            'client_id' => $client->getKey(),
            'technician_id' => $other->getKey(),
        ]);

        Sanctum::actingAs($technician);

        $this->getJson('/api/technician/service-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->uuid)
            ->assertJsonPath('data.0.client.phone', '41999990000');

        $this->getJson("/api/technician/service-orders/{$foreign->uuid}")->assertNotFound();
        $this->getJson('/api/service-orders')->assertForbidden();

        $this->patchJson("/api/technician/service-orders/{$own->uuid}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->post("/api/technician/service-orders/{$own->uuid}/attachments", [
            'file' => UploadedFile::fake()->image('instalacao.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->get("/api/technician/service-orders/{$own->uuid}/pdf")->assertOk();

        $this->patchJson("/api/technician/service-orders/{$own->uuid}/status", [
            'status' => 'completed',
            'execution_notes' => 'Instalado sob o painel.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.execution_notes', 'Instalado sob o painel.');

        $this->getJson('/api/technician/service-orders?scope=history')->assertJsonCount(1, 'data');
    }

    /**
     * @return array{0: Tenant, 1: Client, 2: User, 3: Service}
     */
    private function schedulingScenario(): array
    {
        [, $tenant] = $this->createOperationalChild();

        $client = Client::factory()->for($tenant)->create([
            'street' => 'Rua XV de Novembro',
            'number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Curitiba',
            'state' => 'PR',
            'zip' => '80020-310',
        ]);

        $technician = User::factory()->for($tenant)->technician()->create([
            'city' => 'Curitiba',
            'state' => 'PR',
            'latitude' => -25.40,
            'longitude' => -49.30,
        ]);
        $technician->workHours()->create(['weekday' => 2, 'starts_at' => '08:00', 'ends_at' => '13:00']);

        $service = Service::factory()->forTenant($tenant)->create(['estimated_minutes' => 90]);

        return [$tenant, $client, $technician, $service];
    }

    private function fakeGeoServices(
        int $outboundSeconds = 1800,
        int $returnSeconds = 2400,
        int $outboundDistance = 15000,
        int $returnDistance = 17000,
        ?callable $routeResolver = null,
    ): void {
        config([
            'scheduling.geocoding.enabled' => true,
            'scheduling.routing.enabled' => true,
        ]);

        Http::fake(function ($request) use ($outboundSeconds, $returnSeconds, $outboundDistance, $returnDistance, $routeResolver) {
            $url = $request->url();

            if (str_contains($url, '/search')) {
                return Http::response([['lat' => '-25.4284', 'lon' => '-49.2733']]);
            }

            if (str_contains($url, '/route/v1/')) {
                if ($routeResolver !== null) {
                    $custom = $routeResolver($url);

                    if ($custom !== null) {
                        return $custom;
                    }
                }

                // A volta é consultada no sentido inverso (atendimento → base).
                $isReturn = str_contains($url, '-49.273300,-25.428400;-49.300000,-25.400000');

                return Http::response([
                    'code' => 'Ok',
                    'routes' => [[
                        'duration' => $isReturn ? $returnSeconds : $outboundSeconds,
                        'distance' => $isReturn ? $returnDistance : $outboundDistance,
                        'geometry' => [
                            'coordinates' => $isReturn
                                ? [
                                    [-49.2733, -25.4284],
                                    [-49.2850, -25.4200],
                                    [-49.3000, -25.4000],
                                ]
                                : [
                                    [-49.3000, -25.4000],
                                    [-49.2733, -25.4284],
                                ],
                        ],
                    ]],
                ]);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @param  list<array{0: float, 1: float}>  $geometry
     */
    private function routeResponse(int $seconds, int $meters, array $geometry)
    {
        return Http::response([
            'code' => 'Ok',
            'routes' => [[
                'duration' => $seconds,
                'distance' => $meters,
                'geometry' => ['coordinates' => $geometry],
            ]],
        ]);
    }

    private function localTime(string $time): string
    {
        return CarbonImmutable::parse(self::TUESDAY.' '.$time, 'America/Sao_Paulo')->utc()->toIso8601String();
    }

    private function notificationCount(User $user, string $type): int
    {
        return \DB::table('user_notifications')->where('user_id', $user->getKey())->where('type', $type)->count();
    }
}
