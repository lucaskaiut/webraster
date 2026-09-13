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
use App\Modules\Finance\Services\FinanceNotificationService;
use App\Modules\Finance\Support\FinanceNotificationMessage;

class SendFinanceNotifications
{
    public function __construct(
        private readonly FinanceNotificationService $notifications,
    ) {}

    public function handleInvoiceCreated(InvoiceCreated $event): void
    {
        $this->notifyBilling($event->billing, 'Cobrança gerada', 'Uma nova cobrança foi gerada.');
    }

    public function handleInvoiceDueSoon(InvoiceDueSoon $event): void
    {
        $this->notifyBilling($event->billing, 'Cobrança a vencer', 'Sua cobrança vence em breve.');
    }

    public function handleInvoiceOverdue(InvoiceOverdue $event): void
    {
        $this->notifyBilling($event->billing, 'Cobrança vencida', 'Sua cobrança está em atraso.');
    }

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $this->notifyBilling($event->billing, 'Pagamento confirmado', 'Recebemos o pagamento da sua cobrança.');
    }

    public function handleInvoiceCanceled(InvoiceCanceled $event): void
    {
        $this->notifyBilling($event->billing, 'Cobrança cancelada', 'Sua cobrança foi cancelada.');
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->notifySubscription($event->subscription, 'Assinatura ativada', 'Sua assinatura foi ativada.');
    }

    public function handleSubscriptionCanceled(SubscriptionCanceled $event): void
    {
        $this->notifySubscription($event->subscription, 'Assinatura cancelada', 'Sua assinatura foi cancelada.');
    }

    public function handleSubscriptionRenewed(SubscriptionRenewed $event): void
    {
        $this->notifySubscription($event->subscription, 'Assinatura renovada', 'Sua assinatura foi renovada.');
    }

    private function notifyBilling(FinanceBilling $billing, string $subject, string $intro): void
    {
        $billing->loadMissing('client');
        $client = $billing->client;
        $to = $client?->financial_email ?: $client?->email;

        if (blank($to)) {
            return;
        }

        $body = sprintf(
            "%s\n\nCódigo: %s\nValor: R$ %s\nVencimento: %s\n",
            $intro,
            $billing->code,
            number_format($billing->totalCents() / 100, 2, ',', '.'),
            $billing->due_at?->format('d/m/Y') ?? '-',
        );

        $this->notifications->notify(new FinanceNotificationMessage(
            to: (string) $to,
            subject: $subject,
            body: $body,
            data: [
                'billing_id' => $billing->uuid,
                'status' => $billing->status?->value,
            ],
            clientId: $client?->getKey(),
        ));
    }

    private function notifySubscription(FinanceSubscription $subscription, string $subject, string $intro): void
    {
        $subscription->loadMissing('client');
        $client = $subscription->client;
        $to = $client?->financial_email ?: $client?->email;

        if (blank($to)) {
            return;
        }

        $body = sprintf(
            "%s\n\nStatus: %s\nPróxima cobrança: %s\n",
            $intro,
            $subscription->status?->label() ?? '-',
            $subscription->next_billing_at?->format('d/m/Y') ?? '-',
        );

        $this->notifications->notify(new FinanceNotificationMessage(
            to: (string) $to,
            subject: $subject,
            body: $body,
            data: [
                'subscription_id' => $subscription->uuid,
                'status' => $subscription->status?->value,
            ],
            clientId: $client?->getKey(),
        ));
    }
}
