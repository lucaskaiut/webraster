<?php

namespace Tests\Unit\Billing;

use App\Modules\Billing\Enums\RecurrenceUnit;
use App\Modules\Billing\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PlanNextBillingTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_calculate_next_billing_adds_recurrence_days(): void
    {
        $tenant = $this->createTenantWithRoles();

        $plan = Plan::factory()->forTenant($tenant)->create([
            'recurrence_value' => 30,
            'recurrence_unit' => RecurrenceUnit::DAYS,
        ]);

        $from = CarbonImmutable::parse('2026-01-01 10:00:00');

        $this->assertSame(
            '2026-01-31 10:00:00',
            $plan->calculateNextBillingFrom($from)->format('Y-m-d H:i:s'),
        );
    }
}
