<?php

namespace Tests\Feature\Notification;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Events\InvoicePaid;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Notification\Jobs\SendPushNotificationJob;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinanceNotificationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_invoice_paid_notifies_client_users_with_push(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $user = $this->createClient($tenant, ['client_id' => $client->getKey()]);

        $subscription = FinanceSubscription::factory()->forClient($client)->create();

        $billing = FinanceBilling::factory()->forSubscription($subscription)->create([
            'status' => BillingStatus::PAID,
            'paid_at' => now(),
        ]);

        Queue::fake();

        InvoicePaid::dispatch($billing);

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'source' => 'finance',
            'type' => 'finance_invoice_paid',
            'title' => 'Pagamento confirmado',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_finance_notification_ignores_users_from_other_clients(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $client = Client::factory()->for($tenant)->create();
        $otherClient = Client::factory()->for($tenant)->create();
        $outsider = $this->createClient($tenant, ['client_id' => $otherClient->getKey()]);

        $subscription = FinanceSubscription::factory()->forClient($client)->create();

        $billing = FinanceBilling::factory()->forSubscription($subscription)->create();

        Queue::fake();

        InvoicePaid::dispatch($billing);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $outsider->getKey(),
        ]);
    }
}
