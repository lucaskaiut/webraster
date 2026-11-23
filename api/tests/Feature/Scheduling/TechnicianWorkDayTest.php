<?php

namespace Tests\Feature\Scheduling;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TechnicianWorkDayTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    // Terça-feira.
    private const DATE = '2026-10-20';

    protected function setUp(): void
    {
        parent::setUp();

        // Segunda-feira, 06:00 em São Paulo.
        $this->travelTo(CarbonImmutable::parse('2026-10-19 06:00:00', 'America/Sao_Paulo'));
    }

    public function test_shows_regular_work_day(): void
    {
        [, $technician] = $this->scenario();
        Sanctum::actingAs($technician);

        $this->getJson('/api/technician/work-day?date='.self::DATE)
            ->assertOk()
            ->assertJsonPath('data.has_work', true)
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.regular.starts_at', '10:00')
            ->assertJsonPath('data.regular.ends_at', '18:00')
            ->assertJsonPath('data.anticipated_to', null)
            ->assertJsonPath('data.extended_to', null)
            ->assertJsonCount(2, 'data.intervals');
    }

    public function test_anticipates_and_extends_only_first_and_last_intervals(): void
    {
        [$tenant, $technician] = $this->scenario();
        Sanctum::actingAs($technician);

        $this->putJson('/api/technician/work-day', [
            'date' => self::DATE,
            'starts_at' => '08:00',
            'ends_at' => '20:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.anticipated_to', '08:00')
            ->assertJsonPath('data.extended_to', '20:00')
            ->assertJsonPath('data.effective.starts_at', '08:00')
            ->assertJsonPath('data.effective.ends_at', '20:00')
            ->assertJsonPath('data.intervals.0.ends_at', '12:00')
            ->assertJsonPath('data.intervals.1.starts_at', '13:00');

        // O agendamento passa a oferecer os horários extras.
        Sanctum::actingAs($this->createAdmin($tenant));
        $client = Client::factory()->for($tenant)->create();

        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => $this->localTime('08:00'),
            'estimated_duration_minutes' => 60,
        ])->assertCreated();

        // Outro dia (sem ajuste) continua bloqueando antes das 10:00.
        $this->postJson('/api/service-orders', [
            'client_id' => $client->uuid,
            'technician_id' => $technician->uuid,
            'scheduled_start_at' => CarbonImmutable::parse('2026-10-27 08:00', 'America/Sao_Paulo')->utc()->toIso8601String(),
            'estimated_duration_minutes' => 60,
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_start_at');
    }

    public function test_rejects_shortening_past_dates_and_days_off(): void
    {
        [, $technician] = $this->scenario();
        Sanctum::actingAs($technician);

        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => '10:30', 'ends_at' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => null, 'ends_at' => '17:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_at');

        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => '04:00', 'ends_at' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => '08:07', 'ends_at' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->putJson('/api/technician/work-day', ['date' => '2026-10-18', 'starts_at' => '08:00', 'ends_at' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        // Quarta-feira sem jornada cadastrada.
        $this->putJson('/api/technician/work-day', ['date' => '2026-10-21', 'starts_at' => '08:00', 'ends_at' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        // Hoje é permitido.
        $this->putJson('/api/technician/work-day', ['date' => '2026-10-19', 'starts_at' => '07:00', 'ends_at' => null])
            ->assertOk()->assertJsonPath('data.anticipated_to', '07:00');
    }

    public function test_blocks_undo_when_order_uses_extra_hours(): void
    {
        [$tenant, $technician] = $this->scenario();
        Sanctum::actingAs($technician);

        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => '08:00', 'ends_at' => '20:00'])
            ->assertOk();

        $client = Client::factory()->for($tenant)->create();
        ServiceOrder::factory()->for($tenant)->create([
            'number' => 174,
            'client_id' => $client->getKey(),
            'technician_id' => $technician->getKey(),
            'status' => ServiceOrderStatus::OPEN->value,
            'scheduled_start_at' => $this->localTime('08:30'),
            'scheduled_end_at' => $this->localTime('09:10'),
        ]);

        $response = $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => null, 'ends_at' => '20:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->assertStringContainsString('OS 174 (08:30–09:10)', $response->json('errors.date.0'));

        // Antecipar ainda mais cedo é permitido; a extensão pode ser desfeita.
        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => '07:00', 'ends_at' => null])
            ->assertOk()
            ->assertJsonPath('data.anticipated_to', '07:00')
            ->assertJsonPath('data.extended_to', null);

        // Cancelada, a OS não bloqueia mais.
        ServiceOrder::withoutGlobalScopes()->update(['status' => ServiceOrderStatus::CANCELLED->value]);

        $this->putJson('/api/technician/work-day', ['date' => self::DATE, 'starts_at' => null, 'ends_at' => null])
            ->assertOk()
            ->assertJsonPath('data.anticipated_to', null);

        $this->assertDatabaseCount('user_work_day_overrides', 0);
    }

    public function test_requires_execute_permission(): void
    {
        [$tenant] = $this->scenario();
        $other = User::factory()->for($tenant)->create();
        Sanctum::actingAs($other);

        $this->getJson('/api/technician/work-day?date='.self::DATE)->assertForbidden();
    }

    /**
     * Técnico com jornada de terça 10:00–12:00 e 13:00–18:00.
     *
     * @return array{0: Tenant, 1: User}
     */
    private function scenario(): array
    {
        [, $tenant] = $this->createOperationalChild();

        $technician = User::factory()->for($tenant)->technician()->create();
        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));
        $technician->workHours()->create(['weekday' => 2, 'starts_at' => '13:00', 'ends_at' => '18:00']);
        $technician->workHours()->create(['weekday' => 2, 'starts_at' => '10:00', 'ends_at' => '12:00']);
        // Segunda (hoje) 09:00–18:00.
        $technician->workHours()->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);

        return [$tenant, $technician];
    }

    private function localTime(string $time): string
    {
        return CarbonImmutable::parse(self::DATE.' '.$time, 'America/Sao_Paulo')->utc()->toIso8601String();
    }
}
