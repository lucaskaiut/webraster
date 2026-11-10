<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\CollectionReminderStage;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceCollectionSetting;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Enums\TenantDomainStatus;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Régua de cobrança: envia ao cliente os avisos configurados pela empresa
 * antes do vencimento, no dia e, enquanto houver atraso, a cada X dias.
 *
 * Cada envio fica registrado no histórico da cobrança, o que torna a rotina
 * idempotente: rodar mais de uma vez no mesmo dia não repete avisos.
 */
class CollectionReminderService
{
    public function __construct(
        private readonly FinanceCollectionSettingService $settings,
        private readonly FinanceClientNotifier $notifier,
        private readonly FinanceBillingService $billings,
    ) {}

    /**
     * Processa a empresa do TenantContext atual. Retorna quantos avisos saíram.
     */
    public function process(?CarbonImmutable $today = null): int
    {
        $setting = $this->settings->current();

        if (! $setting->exists || ! $setting->is_active) {
            return 0;
        }

        $today ??= CarbonImmutable::now((string) config('scheduling.timezone'))->startOfDay();
        $sent = 0;

        FinanceBilling::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->whereNotNull('due_at')
            ->whereIn('status', [
                BillingStatus::PENDING->value,
                BillingStatus::AWAITING_PAYMENT->value,
                BillingStatus::OVERDUE->value,
            ])
            ->whereDate('due_at', '<=', $today->addDays(max(0, $setting->before_due_days))->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($billings) use ($setting, $today, &$sent): void {
                foreach ($billings as $billing) {
                    /** @var FinanceBilling $billing */
                    $stage = $this->stageFor($billing, $setting, $today);

                    if ($stage !== null && $this->send($billing, $stage, $setting, $today)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Etapa da régua que vale hoje para a cobrança, se alguma.
     */
    public function stageFor(
        FinanceBilling $billing,
        FinanceCollectionSetting $setting,
        CarbonImmutable $today,
    ): ?CollectionReminderStage {
        $dueAt = CarbonImmutable::parse($billing->due_at->toDateString(), $today->getTimezone())->startOfDay();
        $days = (int) $today->diffInDays($dueAt, false);

        if ($days > 0) {
            return $setting->before_due_enabled && $days === $setting->before_due_days
                ? CollectionReminderStage::BEFORE_DUE
                : null;
        }

        if ($days === 0) {
            return $setting->due_date_enabled ? CollectionReminderStage::DUE_DATE : null;
        }

        if (! $setting->overdue_enabled) {
            return null;
        }

        // Primeiro aviso no dia seguinte ao vencimento; depois, a cada X dias.
        $daysLate = -$days;
        $interval = max(1, $setting->overdue_interval_days);

        if (($daysLate - 1) % $interval !== 0) {
            return null;
        }

        $max = $setting->overdue_max_reminders;

        if ($max !== null && $this->sentCount($billing, CollectionReminderStage::OVERDUE) >= $max) {
            return null;
        }

        return CollectionReminderStage::OVERDUE;
    }

    /**
     * Link para o cliente pagar: a fatura do gateway (Asaas) quando já
     * existe; senão, a cobrança no portal do painel, onde ele gera o pagamento.
     */
    public function paymentUrl(FinanceBilling $billing): string
    {
        if (filled($billing->invoice_url)) {
            return (string) $billing->invoice_url;
        }

        $tenant = TenantContext::tenant();
        $base = $tenant !== null && $tenant->domain_status === TenantDomainStatus::ACTIVE
            ? $tenant->domainUrl()
            : null;
        $base = rtrim((string) ($base ?: config('app.frontend_url')), '/');

        return "{$base}/finance/portal/billings/{$billing->uuid}";
    }

    /**
     * Substitui as variáveis do texto configurado.
     */
    public function render(string $template, FinanceBilling $billing, CarbonImmutable $today): string
    {
        $dueAt = CarbonImmutable::parse($billing->due_at->toDateString(), $today->getTimezone())->startOfDay();

        return strtr($template, [
            '{cliente}' => (string) ($billing->client?->name ?? ''),
            '{empresa}' => (string) (TenantContext::tenant()?->name ?? ''),
            '{codigo}' => $billing->code,
            '{valor}' => 'R$ '.number_format($billing->totalCents() / 100, 2, ',', '.'),
            '{vencimento}' => $dueAt->format('d/m/Y'),
            '{dias_atraso}' => (string) max(0, (int) $dueAt->diffInDays($today, false)),
            '{link}' => $this->paymentUrl($billing),
        ]);
    }

    private function send(
        FinanceBilling $billing,
        CollectionReminderStage $stage,
        FinanceCollectionSetting $setting,
        CarbonImmutable $today,
    ): bool {
        if ($this->alreadySentToday($billing, $stage, $today)) {
            return false;
        }

        $body = $this->render($setting->messageFor($stage), $billing, $today);
        $url = $this->paymentUrl($billing);

        $this->notifier->notify($billing->client, $stage->notificationType(), $stage->subject(), $body, [
            'billing_id' => $billing->uuid,
            'status' => $billing->status?->value,
            'stage' => $stage->value,
            'payment_url' => $url,
        ]);

        $this->billings->recordEvent($billing, $stage->eventAction(), $billing->status, $billing->status, [
            'stage' => $stage->value,
            'sent_on' => $today->toDateString(),
            'payment_url' => $url,
        ]);

        return true;
    }

    private function alreadySentToday(FinanceBilling $billing, CollectionReminderStage $stage, CarbonImmutable $today): bool
    {
        $start = $today->startOfDay()->utc();

        return $billing->events()
            ->where('action', $stage->eventAction())
            ->whereBetween('created_at', [$start, $start->addDay()->subSecond()])
            ->exists();
    }

    private function sentCount(FinanceBilling $billing, CollectionReminderStage $stage): int
    {
        return $billing->events()->where('action', $stage->eventAction())->count();
    }
}
