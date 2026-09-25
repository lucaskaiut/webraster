<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use App\Modules\Finance\DTOs\GatewayPaymentDTO;
use App\Modules\Finance\DTOs\GatewayWebhookEventDTO;
use App\Modules\Finance\Enums\GatewayWebhookEventType;
use App\Modules\Finance\Events\InvoiceOverdue;
use App\Modules\Finance\Exceptions\GatewayException;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceWebhookLog;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentWebhookProcessor
{
    public function __construct(
        private readonly FinanceBillingService $billings,
    ) {}

    public function process(Tenant $tenant, PaymentGatewayInterface $gateway, GatewayWebhookEventDTO $event): void
    {
        TenantContext::set($tenant);

        if (! $gateway->isReady()) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Gateway de pagamento inexistente ou inativo.'],
            ]);
        }

        if ($event->eventId) {
            $existing = FinanceWebhookLog::query()
                ->where('event_id', $event->eventId)
                ->where('status', 'processed')
                ->first();

            if ($existing) {
                return;
            }
        }

        $log = FinanceWebhookLog::query()->create([
            'event_id' => $event->eventId,
            'event' => $event->type->value,
            'payment_id' => $event->paymentExternalId,
            'status' => 'received',
            'payload' => $event->payload,
        ]);

        try {
            $billing = $this->resolveBilling($event);

            match ($event->type) {
                GatewayWebhookEventType::PAYMENT_PAID => $this->handlePaid($gateway, $billing, $event),
                GatewayWebhookEventType::PAYMENT_OVERDUE => $this->handleOverdue($billing),
                GatewayWebhookEventType::PAYMENT_DELETED => $this->handleDeleted($billing),
                GatewayWebhookEventType::PAYMENT_REFUNDED => $this->handleRefund($billing),
                GatewayWebhookEventType::IGNORED => null,
            };

            $log->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            Log::warning('finance.payment.webhook_failed', [
                'tenant_id' => $tenant->getKey(),
                'gateway' => $gateway->key(),
                'event' => $event->type->value,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveBilling(GatewayWebhookEventDTO $event): ?FinanceBilling
    {
        if ($event->paymentExternalId) {
            $byGateway = FinanceBilling::query()
                ->where('gateway_payment_id', $event->paymentExternalId)
                ->first();

            if ($byGateway) {
                return $byGateway;
            }
        }

        if ($event->billingExternalReference) {
            return FinanceBilling::query()->where('uuid', $event->billingExternalReference)->first();
        }

        return null;
    }

    private function handlePaid(
        PaymentGatewayInterface $gateway,
        ?FinanceBilling $billing,
        GatewayWebhookEventDTO $event,
    ): void {
        if ($billing === null || $billing->status === BillingStatus::PAID) {
            return;
        }

        $remote = $this->assertPaymentIsPaidOnGateway($gateway, $billing, $event);
        $this->billings->markPaid($billing, $remote->amountCents());
    }

    private function assertPaymentIsPaidOnGateway(
        PaymentGatewayInterface $gateway,
        FinanceBilling $billing,
        GatewayWebhookEventDTO $event,
    ): GatewayPaymentDTO {
        $paymentId = $event->paymentExternalId ?: (string) $billing->gateway_payment_id;

        if ($paymentId === '') {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Webhook de pagamento sem ID no gateway.'],
            ]);
        }

        if (filled($billing->gateway_payment_id)
            && ! hash_equals((string) $billing->gateway_payment_id, $paymentId)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['O pagamento do webhook não corresponde à cobrança local.'],
            ]);
        }

        try {
            $remote = $gateway->getPayment($paymentId);
        } catch (GatewayException $exception) {
            if ($exception->statusCode >= 500 || $exception->statusCode === 0) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'payment_gateway' => ['Não foi possível confirmar o pagamento no gateway: '.$exception->getMessage()],
            ]);
        }

        if ($remote->externalId === '' || ! hash_equals($paymentId, $remote->externalId)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Resposta do gateway não corresponde ao pagamento informado.'],
            ]);
        }

        if (! $remote->status->isPaid()) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Pagamento ainda não confirmado no gateway (status: '.$remote->status->value.').'],
            ]);
        }

        $remoteExternal = (string) ($remote->externalReference ?? '');
        if ($remoteExternal !== '' && ! hash_equals((string) $billing->uuid, $remoteExternal)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['O pagamento do gateway não pertence a esta cobrança.'],
            ]);
        }

        if (blank($billing->gateway_payment_id)
            && ($remoteExternal === '' || ! hash_equals((string) $billing->uuid, $remoteExternal))) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['O pagamento do gateway não pertence a esta cobrança.'],
            ]);
        }

        return $remote;
    }

    private function handleOverdue(?FinanceBilling $billing): void
    {
        if ($billing === null || $billing->status === BillingStatus::PAID) {
            return;
        }

        if ($billing->status !== BillingStatus::OVERDUE) {
            $from = $billing->status;
            $billing->status = BillingStatus::OVERDUE;
            $billing->save();
            $this->billings->recordEvent($billing, 'webhook_overdue', $from, BillingStatus::OVERDUE);
            InvoiceOverdue::dispatch($billing);
        }
    }

    private function handleDeleted(?FinanceBilling $billing): void
    {
        if ($billing === null || $billing->status === BillingStatus::PAID) {
            return;
        }

        $this->billings->cancel($billing);
    }

    private function handleRefund(?FinanceBilling $billing): void
    {
        if ($billing === null || $billing->status !== BillingStatus::PAID) {
            return;
        }

        $this->billings->refund($billing);
    }
}
