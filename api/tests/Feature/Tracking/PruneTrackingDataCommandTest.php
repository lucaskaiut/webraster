<?php

namespace Tests\Feature\Tracking;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tracking\Enums\TraccarEventStatus;
use App\Modules\Tracking\Models\EquipmentRawLog;
use App\Modules\Tracking\Models\TraccarEvent;
use App\Modules\Vehicle\Models\Vehicle;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PruneTrackingDataCommandTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_removes_only_terminal_old_events(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $old = now()->subDays(45);
        $recent = now()->subDays(5);

        $this->makeEvent($tenant->id, TraccarEventStatus::PROCESSED, $old);
        $this->makeEvent($tenant->id, TraccarEventStatus::IGNORED, $old);
        $this->makeEvent($tenant->id, TraccarEventStatus::FAILED, $old);
        $this->makeEvent($tenant->id, TraccarEventStatus::PENDING, $old);
        $this->makeEvent($tenant->id, TraccarEventStatus::PROCESSING, $old);
        $this->makeEvent($tenant->id, TraccarEventStatus::PROCESSED, $recent);

        $this->artisan('tracking:prune', ['--only' => 'events'])->assertSuccessful();

        $this->assertSame(3, TraccarEvent::query()->count());
        $this->assertSame(1, TraccarEvent::query()->where('status', TraccarEventStatus::PENDING->value)->count());
        $this->assertSame(1, TraccarEvent::query()->where('status', TraccarEventStatus::PROCESSING->value)->count());
        $this->assertSame(1, TraccarEvent::query()->where('status', TraccarEventStatus::PROCESSED->value)->count());
    }

    public function test_dry_run_does_not_delete(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $this->makeEvent($tenant->id, TraccarEventStatus::PROCESSED, now()->subDays(45));

        $this->artisan('tracking:prune', ['--only' => 'events', '--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, TraccarEvent::query()->count());
    }

    public function test_removes_old_raw_logs_and_keeps_recent(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $vehicle = Vehicle::factory()->forClient($client)->create();
        $equipment = Equipment::factory()->assignedTo($vehicle)->create(['imei' => '359633100000042']);

        EquipmentRawLog::query()->create([
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'imei' => $equipment->imei,
            'line' => '*ET,old#',
            'received_at' => now()->subDays(40),
        ]);

        EquipmentRawLog::query()->create([
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'imei' => $equipment->imei,
            'line' => '*ET,recent#',
            'received_at' => now()->subDays(2),
        ]);

        $this->artisan('tracking:prune', ['--only' => 'raw-logs'])->assertSuccessful();

        $this->assertSame(1, EquipmentRawLog::withoutTenancy()->count());
        $this->assertSame(
            now()->subDays(2)->toDateString(),
            EquipmentRawLog::withoutTenancy()->firstOrFail()->received_at->toDateString(),
        );
    }

    public function test_positions_step_is_skipped_when_connection_is_not_configured(): void
    {
        config(['database.connections.traccar.password' => '']);

        $this->artisan('tracking:prune', ['--only' => 'positions'])
            ->expectsOutputToContain('etapa pulada')
            ->assertSuccessful();
    }

    private function makeEvent(int $tenantId, TraccarEventStatus $status, DateTimeInterface $receivedAt): void
    {
        TraccarEvent::query()->create([
            'tenant_id' => $tenantId,
            'traccar_device_id' => 1,
            'dedupe_key' => (string) Str::uuid(),
            'status' => $status->value,
            'payload' => ['type' => 'position'],
            'received_at' => $receivedAt,
        ]);
    }
}
