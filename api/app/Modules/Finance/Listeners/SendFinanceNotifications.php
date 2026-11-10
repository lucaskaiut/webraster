<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Events\InvoiceCanceled;
use App\Modules\Finance\Events\InvoiceCreated;
use App\Modules\Finance\Events\InvoiceDueSoon;
use App\Modules\Finance\Events\InvoiceOverdue;
use App\Modules\Finance\Events\InvoicePaid;
use App\Modules\Finance\Events\SubscriptionCanceled;
use App\Modules\Finance\Events\SubscriptionCreated;
use App\Modules\Finance\Events\SubscriptionRenewed;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\FinanceClientNotifier;
use App\Modules\Finance\Services\FinanceCollectionSettingService;

class SendFinanceNotifications
{
    public function __construct(
        private readonly FinanceClientNotifier $notifier,
        private readonly FinanceCollectionSettingService $collection,
    ) {}

    public function handleInvoiceCreated(InvoiceCreated $event): void
    {
        $billing = $event->billing;
        $this->notifyBilling(
            $billing,
            'finance_invoice_created',
            'Cobrança gerada',
            'Uma nova cobrança foi gerada.',
        );
    }

    public function handleInvoiceDueSoon(InvoiceDueSoon $event): void
    {
        $billing = $event->billing;

        // Com a régua de cobrança ativa, os avisos de vencimento seguem a régua.
        if ($this->collection->isActiveForCurrentTenant()) {
            return;
        }

        $this->notifyBilling(
            $billing,
            'finance_invoice_due_soon',
            'Cobrança a vencer',
            'Sua cobrança vence em breve.',
        );
    }

    public function handleInvoiceOverdue(InvoiceOverdue $event): void
    {
        $billing = $event->billing;

        // Com a régua de cobrança ativa, os avisos de atraso seguem a régua.
        if ($this->collection->isActiveForCurrentTenant()) {
            return;
        }

        $this->notifyBilling(
            $billing,
            'finance_invoice_overdue',
            'Cobrança vencida',
            'Sua cobrança está em atraso.',
        );
    }

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $billing = $event->billing;
        $this->notifyBilling(
            $billing,
            'finance_invoice_paid',
            'Pagamento confirmado',
            'Recebemos o pagamento da sua cobrança.',
        );
    }

    public function handleInvoiceCanceled(InvoiceCanceled $event): void
    {
        $billing = $event->billing;
        $this->notifyBilling(
            $billing,
            'finance_invoice_canceled',
            'Cobrança cancelada',
            'Sua cobrança foi cancelada.',
        );
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription;
        $this->notifySubscription(
            $subscription,
            'finance_subscription_created',
            'Assinatura ativada',
            'Sua assinatura foi ativada.',
        );
    }

    public function handleSubscriptionCanceled(SubscriptionCanceled $event): void
    {
        $subscription = $event->subscription;
        $this->notifySubscription(
            $subscription,
            'finance_subscription_canceled',
            'Assinatura cancelada',
            'Sua assinatura foi cancelada.',
        );
    }

    public function handleSubscriptionRenewed(SubscriptionRenewed $event): void
    {
        $subscription = $event->subscription;
        $this->notifySubscription(
            $subscription,
            'finance_subscription_renewed',
            'Assinatura renovada',
            'Sua assinatura foi renovada.',
        );
    }

    private function notifyBilling(
        FinanceBilling $billing,
        string $type,
        string $subject,
        string $intro,
    ): void {
        $billing->loadMissing('client');
        $client = $billing->client;

        $body = sprintf(
            "%s\n\nCódigo: %s\nValor: R$ %s\nVencimento: %s\n",
            $intro,
            $billing->code,
            number_format($billing->totalCents() / 100, 2, ',', '.'),
            $billing->due_at?->format('d/m/Y') ?? '-',
        );

        $data = [
            'billing_id' => $billing->uuid,
            'status' => $billing->status?->value,
        ];

        $this->notifier->notify($client, $type, $subject, $body, $data);
    }

    private function notifySubscription(
        FinanceSubscription $subscription,
        string $type,
        string $subject,
        string $intro,
    ): void {
        $subscription->loadMissing('client');
        $client = $subscription->client;

        $body = sprintf(
            "%s\n\nStatus: %s\nPróxima cobrança: %s\n",
            $intro,
            $subscription->status?->label() ?? '-',
            $subscription->next_billing_at?->format('d/m/Y') ?? '-',
        );

        $data = [
            'subscription_id' => $subscription->uuid,
            'status' => $subscription->status?->value,
        ];

        $this->notifier->notify($client, $type, $subject, $body, $data);
    }
}
