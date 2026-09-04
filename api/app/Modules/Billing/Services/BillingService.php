<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CreditCardDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\GatewayPaymentStatus;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionEventType;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\PaymentConfirmed;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\PaymentDataRules;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use App\Modules\Billing\Support\SubscriptionSuspensionRules;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private readonly PaymentGatewayResolver $gateways,
        private readonly SubscriptionService $subscriptions,
        private readonly SubscriptionEventService $events,
    ) {}

    /**
     * Gera apenas a fatura local (sem chamar gateway).
     * O usuário escolhe o método depois via initiatePayment.
     */
    public function createLocalInvoice(
        Subscription $subscription,
        ?CarbonImmutable $dueDate = null,
        ?CarbonImmutable $expiresAt = null,
    ): Invoice {
        $subscription->loadMissing(['plan', 'tenant']);

        $this->assertEligibleForInvoice($subscription);

        $openInvoice = $this->findOpenInvoice($subscription);

        if ($openInvoice !== null) {
            return $openInvoice;
        }

        return DB::transaction(function () use ($subscription, $dueDate, $expiresAt): Invoice {
            /** @var Tenant $tenant */
            $tenant = $subscription->tenant;
            $amount = number_format((float) $subscription->plan->price, 2, '.', '');

            $resolvedDue = $dueDate
                ?? ($subscription->next_billing_at
                    ? CarbonImmutable::instance($subscription->next_billing_at)
                    : CarbonImmutable::now());

            $resolvedExpires = $expiresAt ?? $resolvedDue;

            $invoice = Invoice::query()->withoutTenancy()->create([
                'tenant_id' => $tenant->getKey(),
                'subscription_id' => $subscription->getKey(),
                'gateway' => null,
                'amount' => $amount,
                'status' => InvoiceStatus::PENDING,
                'payment_method' => null,
                'external_id' => null,
                'pix_code' => null,
                'pix_qrcode' => null,
                'due_date' => $resolvedDue,
                'paid_at' => null,
                'expires_at' => $resolvedExpires,
                'metadata' => [
                    'awaiting_payment_method' => true,
                ],
            ]);

            $this->events->record($subscription, SubscriptionEventType::INVOICE_GENERATED, [
                'invoice_uuid' => $invoice->uuid,
                'amount' => $amount,
                'due_date' => $resolvedDue->toIso8601String(),
                'awaiting_payment_method' => true,
            ]);

            return $invoice;
        });
    }

    /**
     * Inicia a cobrança no gateway após o usuário escolher o método.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function initiatePayment(
        Invoice $invoice,
        string $paymentGateway,
        array $paymentData = [],
    ): Invoice {
        $this->gateways->assertActive($paymentGateway);

        return DB::transaction(function () use ($invoice, $paymentGateway, $paymentData): Invoice {
            $invoice = Invoice::query()->withoutTenancy()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $invoice->isOpen()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Esta cobrança não está aberta para pagamento.'],
                ]);
            }

            if (filled($invoice->external_id)) {
                return $invoice->loadMissing(['subscription.plan', 'tenant']);
            }

            $subscription = Subscription::query()
                ->withoutTenancy()
                ->lockForUpdate()
                ->findOrFail($invoice->subscription_id);

            $plan = Plan::query()
                ->withoutTenancy()
                ->findOrFail($subscription->plan_id);

            $tenant = Tenant::query()->findOrFail($subscription->tenant_id);

            $gateway = $this->gateways->resolve($paymentGateway);
            $method = $gateway->paymentMethod();
            $dueDate = CarbonImmutable::instance($invoice->due_date ?? now());

            $paymentData = PaymentDataRules::sanitize($paymentData);
            $remoteIp = PaymentDataRules::resolveRemoteIp($paymentData) ?? request()?->ip();
            $token = PaymentDataRules::resolveToken($paymentData);
            $creditCard = CreditCardDTO::tryFromPaymentData($paymentData);
            $installments = PaymentDataRules::resolveInstallments($paymentData);
            $authorizeOnly = PaymentDataRules::resolveAuthorizeOnly($paymentData);
            $recurring = PaymentDataRules::resolveRecurring($paymentData);

            $customerData = new CustomerDataDTO(
                name: $tenant->name,
                email: $tenant->email,
                document: $tenant->document,
                phone: $tenant->phone,
                externalId: $tenant->uuid,
            );

            $customer = $gateway->createCustomer($customerData);

            $gatewayPayment = $gateway->createPayment(new CreatePaymentDTO(
                customerExternalId: $customer->externalId,
                amount: (string) $invoice->amount,
                paymentMethod: $method,
                dueDate: $dueDate,
                description: "Assinatura {$plan->name}",
                metadata: [
                    'subscription_uuid' => $subscription->uuid,
                    'tenant_uuid' => $tenant->uuid,
                    'plan_uuid' => $plan->uuid,
                    'invoice_uuid' => $invoice->uuid,
                    'payment_gateway' => $gateway->key(),
                ],
                token: $token,
                creditCard: $creditCard,
                customer: $customerData,
                remoteIp: $remoteIp,
                installments: $installments,
                authorizeOnly: $authorizeOnly,
                recurring: $recurring,
            ));

            $gatewayMetadata = $gatewayPayment->metadata ?? [];
            $returnedCardToken = is_string($gatewayMetadata['credit_card_token'] ?? null)
                ? trim((string) $gatewayMetadata['credit_card_token'])
                : '';

            // Token de cartão fica na assinatura (genérico), não no metadata da fatura.
            unset($gatewayMetadata['credit_card_token']);

            $invoice->gateway = $gateway->key();
            $invoice->payment_method = $method;
            $invoice->external_id = $gatewayPayment->externalId;
            $invoice->pix_code = $gatewayPayment->pixCode;
            $invoice->pix_qrcode = $gatewayPayment->pixQrcode;
            $invoice->expires_at = $gatewayPayment->expiresAt ?? $invoice->expires_at;
            // Mantém a fatura aberta: applyGatewayPayment aplica status terminal
            // e confirma o ciclo (last_billed_at / next_billing_at) quando PAID.
            $invoice->metadata = [
                ...($invoice->metadata ?? []),
                'awaiting_payment_method' => false,
                'customer_external_id' => $customer->externalId,
                ...$gatewayMetadata,
            ];
            $invoice->save();

            $subscription->payment_gateway = $gateway->key();

            if ($recurring === true) {
                $subscription->recurring = true;

                if ($returnedCardToken !== '') {
                    $subscription->credit_card_token = $returnedCardToken;
                }
            }

            $subscription->save();

            $this->events->record($subscription, SubscriptionEventType::INVOICE_GENERATED, [
                'invoice_uuid' => $invoice->uuid,
                'external_id' => $gatewayPayment->externalId,
                'payment_gateway' => $gateway->key(),
                'action' => 'payment_initiated',
            ]);

            return $this->applyGatewayPayment($invoice, $gatewayPayment);
        });
    }

    /**
     * Cron / geração periódica de cobrança.
     *
     * Se a assinatura for recorrente e tiver token de cartão, tenta cobrar
     * automaticamente no gateway (com retry). Se esgotar as tentativas,
     * mantém a fatura local para o usuário pagar manualmente.
     */
    public function generateInvoice(Subscription $subscription): Invoice
    {
        $subscription->loadMissing(['plan', 'tenant']);

        if ($this->canChargeRecurring($subscription)) {
            return $this->generateRecurringInvoice($subscription);
        }

        return $this->createLocalInvoice($subscription);
    }

    private function canChargeRecurring(Subscription $subscription): bool
    {
        return $subscription->recurring === true
            && filled($subscription->credit_card_token)
            && filled($subscription->payment_gateway);
    }

    private function generateRecurringInvoice(Subscription $subscription): Invoice
    {
        $invoice = $this->createLocalInvoice($subscription);

        if (filled($invoice->external_id)) {
            return $invoice;
        }

        $maxAttempts = max(1, (int) config('billing.recurring_charge_max_attempts', 3));
        $delayMs = max(0, (int) config('billing.recurring_charge_retry_delay_ms', 0));
        $errors = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->initiatePayment(
                    $invoice,
                    (string) $subscription->payment_gateway,
                    [
                        'credit_card_token' => $subscription->credit_card_token,
                        'remote_ip' => $this->resolveRecurringRemoteIp(),
                    ],
                );
            } catch (\Throwable $e) {
                $errors[] = [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ];

                Log::warning('Falha na cobrança recorrente', [
                    'subscription_uuid' => $subscription->uuid,
                    'invoice_uuid' => $invoice->uuid,
                    'payment_gateway' => $subscription->payment_gateway,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);

                $invoice = $invoice->fresh() ?? $invoice;

                if ($attempt < $maxAttempts && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        $this->events->record($subscription, SubscriptionEventType::PAYMENT_FAILED, [
            'invoice_uuid' => $invoice->uuid,
            'action' => 'recurring_charge_exhausted',
            'payment_gateway' => $subscription->payment_gateway,
            'attempts' => $maxAttempts,
            'errors' => $errors,
        ]);

        return $invoice;
    }

    private function resolveRecurringRemoteIp(): string
    {
        $configured = trim((string) config('billing.recurring_remote_ip', ''));

        if ($configured !== '') {
            return $configured;
        }

        return (string) (request()?->ip() ?: '127.0.0.1');
    }

    public function syncInvoiceStatus(Invoice $invoice): Invoice
    {
        if (! $invoice->isOpen()) {
            return $this->reconcileUnconfirmedPaidInvoice($invoice);
        }

        if (blank($invoice->external_id)) {
            return $this->expireLocalInvoiceIfLapsed($invoice);
        }

        $gatewayKey = $invoice->gateway
            ?: $invoice->subscription?->payment_gateway
            ?: throw ValidationException::withMessages([
                'gateway' => ['Invoice sem gateway associado.'],
            ]);

        $gatewayPayment = $this->gateways->resolve($gatewayKey)->getPayment($invoice->external_id);

        return $this->applyGatewayPayment($invoice, $gatewayPayment);
    }

    /**
     * Repara faturas PAID sem paid_at (ex.: cartão confirmado no createPayment
     * antes de applyGatewayPayment / confirmPayment existirem no fluxo).
     */
    public function reconcileUnconfirmedPaidInvoices(): int
    {
        $fixed = 0;

        $invoices = Invoice::query()
            ->withoutTenancy()
            ->where('status', InvoiceStatus::PAID->value)
            ->whereNull('paid_at')
            ->get();

        foreach ($invoices as $invoice) {
            $result = $this->reconcileUnconfirmedPaidInvoice($invoice);
            if ($result->paid_at !== null) {
                $fixed++;
            }
        }

        return $fixed;
    }

    private function reconcileUnconfirmedPaidInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::PAID || $invoice->paid_at !== null) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->withoutTenancy()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status !== InvoiceStatus::PAID || $invoice->paid_at !== null) {
                return $invoice;
            }

            $invoice->paid_at = now();
            $invoice->save();

            $subscription = Subscription::query()
                ->withoutTenancy()
                ->lockForUpdate()
                ->findOrFail($invoice->subscription_id);

            $this->subscriptions->confirmPayment($subscription);
            $this->events->record($subscription, SubscriptionEventType::PAYMENT_CONFIRMED, [
                'invoice_uuid' => $invoice->uuid,
                'external_id' => $invoice->external_id,
                'amount' => (string) $invoice->amount,
                'reconciled' => true,
            ]);

            PaymentConfirmed::dispatch($subscription, $invoice);

            return $invoice->refresh();
        });
    }

    /**
     * Expira faturas locais (sem gateway) cujo prazo passou — mesma regra de PAST_DUE.
     */
    public function expireOverdueLocalInvoices(): int
    {
        $expired = 0;

        $invoices = Invoice::query()
            ->withoutTenancy()
            ->whereIn('status', [InvoiceStatus::PENDING->value, InvoiceStatus::PROCESSING->value])
            ->whereNull('external_id')
            ->get();

        foreach ($invoices as $invoice) {
            $result = $this->expireLocalInvoiceIfLapsed($invoice);
            if ($result->status === InvoiceStatus::EXPIRED) {
                $expired++;
            }
        }

        return $expired;
    }

    public function applyGatewayPayment(Invoice $invoice, GatewayPaymentDTO $payment): Invoice
    {
        return DB::transaction(function () use ($invoice, $payment): Invoice {
            $invoice = Invoice::query()->withoutTenancy()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $invoice->isOpen()) {
                return $invoice;
            }

            $status = $this->mapGatewayStatus($payment->status);

            $invoice->status = $status;
            $invoice->pix_code = $payment->pixCode ?? $invoice->pix_code;
            $invoice->pix_qrcode = $payment->pixQrcode ?? $invoice->pix_qrcode;
            $invoice->expires_at = $payment->expiresAt ?? $invoice->expires_at;

            if ($status === InvoiceStatus::PAID) {
                $invoice->paid_at = now();
                $invoice->save();

                $subscription = Subscription::query()
                    ->withoutTenancy()
                    ->lockForUpdate()
                    ->findOrFail($invoice->subscription_id);

                $this->subscriptions->confirmPayment($subscription);
                $this->events->record($subscription, SubscriptionEventType::PAYMENT_CONFIRMED, [
                    'invoice_uuid' => $invoice->uuid,
                    'external_id' => $payment->externalId,
                    'amount' => $payment->amount,
                ]);

                PaymentConfirmed::dispatch($subscription, $invoice);

                return $invoice->refresh();
            }

            if (in_array($status, [InvoiceStatus::EXPIRED, InvoiceStatus::FAILED], true)) {
                $invoice->save();

                $subscription = Subscription::query()
                    ->withoutTenancy()
                    ->lockForUpdate()
                    ->findOrFail($invoice->subscription_id);

                $this->subscriptions->markPastDue($subscription);

                return $invoice->refresh();
            }

            $invoice->save();

            return $invoice->refresh();
        });
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('subscription.plan')
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    public function openInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::query()
            ->withoutTenancy()
            ->with('subscription')
            ->whereIn('status', [
                InvoiceStatus::PENDING->value,
                InvoiceStatus::PROCESSING->value,
            ])
            ->get();
    }

    /**
     * @return list<Subscription>
     */
    public function suspendEligibleSubscriptions(): array
    {
        $suspended = [];

        $candidates = Subscription::query()
            ->withoutTenancy()
            ->with('invoices')
            ->whereIn('status', [
                SubscriptionStatus::PAST_DUE->value,
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIALING->value,
            ])
            ->get();

        foreach ($candidates as $subscription) {
            if ($this->shouldSuspend($subscription)) {
                $suspended[] = $this->subscriptions->suspend($subscription, [
                    'reason' => 'automatic_dunning',
                    'max_expired_invoices' => SubscriptionSuspensionRules::MAX_EXPIRED_INVOICES,
                    'days_without_payment' => SubscriptionSuspensionRules::DAYS_WITHOUT_PAYMENT,
                ]);
            }
        }

        return $suspended;
    }

    public function shouldSuspend(Subscription $subscription): bool
    {
        $expiredCount = Invoice::query()
            ->withoutTenancy()
            ->where('subscription_id', $subscription->getKey())
            ->where('status', InvoiceStatus::EXPIRED->value)
            ->count();

        if ($expiredCount >= SubscriptionSuspensionRules::MAX_EXPIRED_INVOICES) {
            return true;
        }

        $reference = $subscription->last_billed_at ?? $subscription->started_at;

        if ($reference === null) {
            return false;
        }

        $daysWithoutPayment = CarbonImmutable::instance($reference)
            ->diffInDays(CarbonImmutable::now());

        return $daysWithoutPayment >= SubscriptionSuspensionRules::DAYS_WITHOUT_PAYMENT
            && in_array($subscription->status, [
                SubscriptionStatus::PAST_DUE,
                SubscriptionStatus::TRIALING,
            ], true);
    }

    private function assertEligibleForInvoice(Subscription $subscription): void
    {
        if (! in_array($subscription->status, [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::TRIALING,
            SubscriptionStatus::PAST_DUE,
        ], true)) {
            throw ValidationException::withMessages([
                'subscription' => ['Assinatura não elegível para geração de cobrança.'],
            ]);
        }

        $leadDays = max(0, (int) config('billing.days_before_due', 0));

        if (
            $subscription->isOnTrial()
            && $subscription->next_billing_at !== null
            && CarbonImmutable::instance($subscription->next_billing_at)->gt(now()->addDays($leadDays))
        ) {
            throw ValidationException::withMessages([
                'subscription' => ['Cobrança disponível apenas na janela de cobrança do período de teste.'],
            ]);
        }
    }

    private function findOpenInvoice(Subscription $subscription): ?Invoice
    {
        return Invoice::query()
            ->withoutTenancy()
            ->where('subscription_id', $subscription->getKey())
            ->whereIn('status', [InvoiceStatus::PENDING->value, InvoiceStatus::PROCESSING->value])
            ->first();
    }

    private function expireLocalInvoiceIfLapsed(Invoice $invoice): Invoice
    {
        if (! $invoice->isOpen() || filled($invoice->external_id)) {
            return $invoice;
        }

        $deadline = $invoice->expires_at ?? $invoice->due_date;

        if ($deadline === null || ! $deadline->isPast()) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->withoutTenancy()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $invoice->isOpen() || filled($invoice->external_id)) {
                return $invoice;
            }

            $deadline = $invoice->expires_at ?? $invoice->due_date;

            if ($deadline === null || ! $deadline->isPast()) {
                return $invoice;
            }

            $invoice->status = InvoiceStatus::EXPIRED;
            $invoice->save();

            $subscription = Subscription::query()
                ->withoutTenancy()
                ->lockForUpdate()
                ->findOrFail($invoice->subscription_id);

            $this->subscriptions->markPastDue($subscription);

            return $invoice->refresh();
        });
    }

    private function mapGatewayStatus(GatewayPaymentStatus $status): InvoiceStatus
    {
        return match ($status) {
            GatewayPaymentStatus::PENDING => InvoiceStatus::PENDING,
            GatewayPaymentStatus::PROCESSING => InvoiceStatus::PROCESSING,
            GatewayPaymentStatus::PAID => InvoiceStatus::PAID,
            GatewayPaymentStatus::EXPIRED => InvoiceStatus::EXPIRED,
            GatewayPaymentStatus::FAILED => InvoiceStatus::FAILED,
            GatewayPaymentStatus::CANCELLED => InvoiceStatus::CANCELLED,
        };
    }
}
