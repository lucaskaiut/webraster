<?php

namespace Tests\Feature\Billing;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Com billing.enabled desligado (padrão), o umbrella não cobra os filhos.
 */
class BillingDisabledTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_subscription_and_invoice_routes_are_unavailable(): void
    {
        [, $child] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($child));

        $this->getJson('/api/billing/subscription')->assertNotFound();
        $this->getJson('/api/billing/invoices')->assertNotFound();
        $this->postJson('/api/billing/subscription', [])->assertNotFound();
    }

    public function test_billing_commands_do_not_run_on_schedule(): void
    {
        $billingEvents = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'billing:'));

        $this->assertCount(3, $billingEvents);
        $billingEvents->each(fn (Event $event) => $this->assertFalse($event->filtersPass($this->app)));

        config(['billing.enabled' => true]);
        $billingEvents->each(fn (Event $event) => $this->assertTrue($event->filtersPass($this->app)));
    }
}
