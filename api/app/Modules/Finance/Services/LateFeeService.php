<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinanceBilling;
use Carbon\CarbonImmutable;

/**
 * Calcula e persiste multa e juros de mora de cobranças vencidas,
 * conforme os percentuais configurados na empresa (tenant).
 *
 * Multa: percentual único sobre o valor devido.
 * Juros: percentual ao mês aplicado proporcionalmente aos dias de atraso
 * (base de 30 dias).
 */
class LateFeeService
{
    private const DAYS_PER_MONTH = 30;

    /**
     * Recalcula os encargos da cobrança e persiste quando houver mudança.
     *
     * Por padrão não altera cobranças que já possuem pagamento emitido no
     * gateway (o valor está congelado no documento gerado). Use $force para
     * recalcular antes de emitir uma nova cobrança.
     */
    public function sync(
        FinanceBilling $billing,
        bool $force = false,
        ?CarbonImmutable $on = null,
    ): FinanceBilling {
        if (! $billing->status->isOpen()) {
            return $billing;
        }

        if (! $force && filled($billing->gateway_payment_id)) {
            return $billing;
        }

        [$fine, $interest] = $this->calculate($billing, $on);

        $billing->fill([
            'fine_cents' => $fine,
            'interest_cents' => $interest,
        ]);

        if ($billing->isDirty(['fine_cents', 'interest_cents'])) {
            $billing->save();
        }

        return $billing;
    }

    /**
     * @return array{0: int, 1: int} [multa em centavos, juros em centavos]
     */
    public function calculate(FinanceBilling $billing, ?CarbonImmutable $on = null): array
    {
        $due = $billing->due_at?->toImmutable()->startOfDay();
        $today = ($on ?? CarbonImmutable::today())->startOfDay();

        if ($due === null || ! $due->lessThan($today)) {
            return [0, 0];
        }

        $tenant = $billing->tenant;
        $finePercent = (float) ($tenant?->late_fine_percent ?? 0);
        $interestPercent = (float) ($tenant?->late_interest_percent ?? 0);

        if ($finePercent <= 0 && $interestPercent <= 0) {
            return [0, 0];
        }

        $baseCents = max(0, (int) $billing->amount_cents - (int) $billing->discount_cents);
        $daysLate = max(0, (int) $due->diffInDays($today));

        return [
            (int) round($baseCents * $finePercent / 100),
            (int) round($baseCents * $interestPercent / 100 * $daysLate / self::DAYS_PER_MONTH),
        ];
    }
}
