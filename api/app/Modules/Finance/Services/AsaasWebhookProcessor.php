<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceWebhookLog;
use App\Modules\Finance\Models\TenantAsaasConfig;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AsaasWebhookProcessor
{
    public function __construct(
        private readonly FinanceReceivableService $receivables,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(Tenant $tenant, array $payload, ?string $accessToken): void
    {
        TenantContext::set($tenant);

        $config = TenantAsaasConfig::query()->first();

        if ($config === null || ! $config->is_active) {
            throw ValidationException::withMessages([
                'asaas' => ['Configuração Asaas inexistente ou inativa.'],
            ]);
        }

        if (blank($accessToken) || ! hash_equals((string) $config->webhook_token, (string) $accessToken)) {
            throw ValidationException::withMessages([
                'asaas' => ['Token de webhook inválido.'],
            ]);
        }

        $event = (string) ($payload['event'] ?? '');
        $eventId = isset($payload['id']) ? (string) $payload['id'] : null;
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $paymentId = isset($payment['id']) ? (string) $payment['id'] : null;

        if ($eventId) {
            $existing = FinanceWebhookLog::query()
                ->where('event_id', $eventId)
                ->where('status', 'processed')
                ->first();

            if ($existing) {
                return;
            }
        }

        $log = FinanceWebhookLog::query()->create([
            'event_id' => $eventId,
            'event' => $event,
            'payment_id' => $paymentId,
            'status' => 'received',
            'payload' => $payload,
        ]);

        try {
            $receivable = $this->resolveReceivable($payment);

            match ($event) {
                'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->handlePaid($receivable, $payment),
                'PAYMENT_OVERDUE' => $this->handleOverdue($receivable),
                'PAYMENT_DELETED' => $this->handleDeleted($receivable),
                'PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED' => $this->handleRefund($receivable),
                default => null,
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

            Log::warning('finance.asaas.webhook_failed', [
                'tenant_id' => $tenant->getKey(),
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function resolveReceivable(array $payment): ?FinanceReceivable
    {
        $paymentId = isset($payment['id']) ? (string) $payment['id'] : null;
        $external = isset($payment['externalReference']) ? (string) $payment['externalReference'] : null;

        if ($paymentId) {
            $byGateway = FinanceReceivable::query()
                ->where('gateway_payment_id', $paymentId)
                ->first();

            if ($byGateway) {
                return $byGateway;
            }
        }

        if ($external) {
            return FinanceReceivable::query()->where('uuid', $external)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function handlePaid(?FinanceReceivable $receivable, array $payment): void
    {
        if ($receivable === null) {
            return;
        }

        $value = isset($payment['value']) ? (int) round(((float) $payment['value']) * 100) : null;
        $this->receivables->markReceived($receivable, $value);
    }

    private function handleOverdue(?FinanceReceivable $receivable): void
    {
        if ($receivable === null || $receivable->status === ReceivableStatus::RECEIVED) {
            return;
        }

        if ($receivable->status !== ReceivableStatus::OVERDUE) {
            $from = $receivable->status;
            $receivable->status = ReceivableStatus::OVERDUE;
            $receivable->save();
            $this->receivables->recordEvent($receivable, 'webhook_overdue', $from, ReceivableStatus::OVERDUE);
        }
    }

    private function handleDeleted(?FinanceReceivable $receivable): void
    {
        if ($receivable === null || $receivable->status === ReceivableStatus::RECEIVED) {
            return;
        }

        $this->receivables->cancel($receivable);
    }

    private function handleRefund(?FinanceReceivable $receivable): void
    {
        if ($receivable === null) {
            return;
        }

        if ($receivable->status === ReceivableStatus::RECEIVED) {
            $this->receivables->refund($receivable);
        }
    }
}
