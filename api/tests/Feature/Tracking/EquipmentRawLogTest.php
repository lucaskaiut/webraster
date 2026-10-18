<?php

namespace Tests\Feature\Tracking;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Models\EquipmentRawLog;
use App\Modules\Tracking\Services\EquipmentRawLogService;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Lottery;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class EquipmentRawLogTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_tail_returns_lines_after_cursor(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createAdmin($tenant);
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create(['imei' => '359633100000001']);

        $service = app(EquipmentRawLogService::class);
        $service->appendFromPositionAttributes($equipment, ['raw' => '*ET,359633100000001,HB,A#'], now());
        $service->appendFromPositionAttributes($equipment, ['raw' => '*ET,359633100000001,CC,A#'], now());

        Sanctum::actingAs($user);

        $initial = $this->getJson("/api/equipments/{$equipment->uuid}/raw-logs")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $initial['lines']);
        $this->assertSame(2, $initial['last_id']);

        $delta = $this->getJson("/api/equipments/{$equipment->uuid}/raw-logs?after_id={$initial['last_id']}")
            ->assertOk()
            ->json('data');

        $this->assertSame([], $delta['lines']);

        $service->appendFromPositionAttributes($equipment, ['raw' => '*ET,359633100000001,TX,A#'], now());

        $delta = $this->getJson("/api/equipments/{$equipment->uuid}/raw-logs?after_id={$initial['last_id']}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $delta['lines']);
        $this->assertStringContainsString('TX', $delta['lines'][0]['line']);
    }

    public function test_forbidden_without_equipment_details_permission(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $user = $this->createAdmin($tenant);
        $user->roles()->firstOrFail()->revokePermissions(Permission::EQUIPMENT_DETAILS_READ);

        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/equipments/{$equipment->uuid}/raw-logs")->assertForbidden();
    }

    public function test_skips_duplicate_consecutive_lines(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create();

        $service = app(EquipmentRawLogService::class);
        $line = '*ET,123,HB,A#';
        $service->appendFromPositionAttributes($equipment, ['raw' => $line], now());
        $service->appendFromPositionAttributes($equipment, ['raw' => $line], now());

        $this->assertSame(1, EquipmentRawLog::query()->where('equipment_id', $equipment->id)->count());
    }

    public function test_prune_trims_history_to_the_limit_when_drawn(): void
    {
        [$equipment, $other] = $this->equipmentsWithFullHistory();

        Lottery::alwaysWin(function () use ($equipment): void {
            app(EquipmentRawLogService::class)->appendFromPositionAttributes($equipment, ['raw' => '*ET,NEW#'], now());
        });

        $remaining = EquipmentRawLog::query()->withoutTenancy()->where('equipment_id', $equipment->id);

        $this->assertSame(EquipmentRawLogService::MAX_LINES_PER_EQUIPMENT, (clone $remaining)->count());
        $this->assertSame('*ET,NEW#', (clone $remaining)->orderByDesc('id')->value('line'));
        $this->assertSame(
            EquipmentRawLogService::MAX_LINES_PER_EQUIPMENT + 5,
            EquipmentRawLog::query()->withoutTenancy()->where('equipment_id', $other->id)->count(),
        );
    }

    public function test_prune_is_skipped_when_not_drawn(): void
    {
        [$equipment] = $this->equipmentsWithFullHistory();

        Lottery::alwaysLose(function () use ($equipment): void {
            app(EquipmentRawLogService::class)->appendFromPositionAttributes($equipment, ['raw' => '*ET,NEW#'], now());
        });

        $this->assertSame(
            EquipmentRawLogService::MAX_LINES_PER_EQUIPMENT + 6,
            EquipmentRawLog::query()->withoutTenancy()->where('equipment_id', $equipment->id)->count(),
        );
    }

    /**
     * Dois equipamentos do mesmo tenant, cada um com histórico acima do limite.
     *
     * @return array{0: Equipment, 1: Equipment}
     */
    private function equipmentsWithFullHistory(): array
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $equipments = [
            Equipment::factory()->assignedTo(Vehicle::factory()->forClient($client)->create())->create(),
            Equipment::factory()->assignedTo(Vehicle::factory()->forClient($client)->create())->create(),
        ];

        foreach ($equipments as $equipment) {
            $rows = [];

            for ($i = 1; $i <= EquipmentRawLogService::MAX_LINES_PER_EQUIPMENT + 5; $i++) {
                $rows[] = [
                    'tenant_id' => $equipment->tenant_id,
                    'equipment_id' => $equipment->id,
                    'imei' => (string) $equipment->imei,
                    'line' => "*ET,OLD,{$i}#",
                    'received_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                EquipmentRawLog::query()->withoutTenancy()->insert($chunk);
            }
        }

        return $equipments;
    }
}
