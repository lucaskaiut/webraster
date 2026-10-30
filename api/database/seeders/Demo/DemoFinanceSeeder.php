<?php

namespace Database\Seeders\Demo;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceBillingEvent;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cobranças das assinaturas desde a contratação: pagas (em dia ou com
 * multa e juros), em aberto, vencidas, canceladas e estornadas. Clientes
 * suspensos têm os rastreadores bloqueados por inadimplência.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(DemoContext $ctx): void
    {
        $finance = $ctx->staff[3];

        foreach ($ctx->clients as $index => $entry) {
            $subscription = $entry['subscription'];

            if ($subscription === null) {
                continue;
            }

            $dues = $this->dueDates($ctx, $subscription, $entry['since']);
            $overdueCount = match ($entry['profile']) {
                'overdue' => 1,
                'suspended' => 3,
                default => 0,
            };
            $pastDues = array_values(array_filter($dues, fn (CarbonImmutable $due) => $due->lessThan($ctx->now->startOfDay())));
            $overdueFrom = count($pastDues) - $overdueCount;
            $last = null;

            foreach ($dues as $position => $due) {
                $status = match (true) {
                    $subscription->cancelled_at !== null && $due->greaterThan($subscription->cancelled_at) => BillingStatus::CANCELLED,
                    $due->greaterThanOrEqualTo($ctx->now->startOfDay()) => BillingStatus::PENDING,
                    $position >= $overdueFrom => BillingStatus::OVERDUE,
                    default => BillingStatus::PAID,
                };

                // Situações pontuais para enriquecer a listagem.
                if ($status === BillingStatus::PAID && $index === 4 && $position === 2) {
                    $status = BillingStatus::REFUNDED;
                }

                if ($status === BillingStatus::PAID && $index % 6 === 1 && $position === 1) {
                    $this->billing($ctx, $subscription, $due, BillingStatus::CANCELLED, $finance, 'Cobrança duplicada — cancelada');
                }

                $last = $this->billing($ctx, $subscription, $due, $status, $finance);

                if ($subscription->cancelled_at !== null && $status === BillingStatus::CANCELLED) {
                    break;
                }
            }

            $this->updateSubscriptionDates($subscription, $dues, $last);

            if ($entry['profile'] === 'suspended') {
                $this->suspendEquipments($ctx, $entry['client']->getKey(), $pastDues[$overdueFrom] ?? $ctx->now->subDays(40));
            }
        }
    }

    /**
     * Vencimentos desde o início da assinatura até o próximo ciclo.
     *
     * @return list<CarbonImmutable>
     */
    private function dueDates(DemoContext $ctx, FinanceSubscription $subscription, CarbonImmutable $since): array
    {
        $annual = $subscription->plan_periodicity === BillingPeriodicity::ANNUAL;
        $due = $since->startOfMonth()->setDay(min($subscription->due_day, 28))->startOfDay();

        if ($due->lessThan($since->addDays(5)->startOfDay())) {
            $due = $due->addMonthNoOverflow();
        }

        $limit = $ctx->now->addDays($annual ? 1 : 32);
        $dues = [];

        while ($due->lessThanOrEqualTo($limit)) {
            $dues[] = $due;
            $due = $annual ? $due->addYear() : $due->addMonthNoOverflow();
        }

        // Plano anual contratado há menos de um ano: só a primeira cobrança.
        return $dues === [] ? [$since->addDays(5)->startOfDay()] : $dues;
    }

    private function billing(
        DemoContext $ctx,
        FinanceSubscription $subscription,
        CarbonImmutable $due,
        BillingStatus $status,
        User $finance,
        ?string $description = null,
    ): FinanceBilling {
        $faker = $ctx->faker;
        $amount = (int) $subscription->plan_price_cents;
        $createdAt = $due->subDays(10)->setTime(6, 0);
        $createdAt = $createdAt->lessThan($subscription->started_at) ? CarbonImmutable::parse($subscription->started_at) : $createdAt;

        $paidAt = null;
        $fine = 0;
        $interest = 0;
        $method = null;

        if (in_array($status, [BillingStatus::PAID, BillingStatus::REFUNDED], true)) {
            $late = $faker->numberBetween(1, 100) <= 12;
            $paidAt = $late
                ? $due->addDays($faker->numberBetween(2, 15))
                : $due->subDays($faker->numberBetween(0, 4));
            $paidAt = $paidAt->setTime($faker->numberBetween(7, 21), $faker->numberBetween(0, 59));

            if ($paidAt->greaterThan($ctx->now)) {
                $paidAt = $ctx->now->subHours($faker->numberBetween(2, 30));
            }

            if ($late) {
                $days = (int) $due->diffInDays($paidAt);
                $fine = (int) round($amount * 0.02);
                $interest = (int) round($amount * 0.01 * $days / 30);
            }

            $method = $faker->randomElement([PaymentMethod::PIX, PaymentMethod::PIX, PaymentMethod::BOLETO, PaymentMethod::BOLETO, PaymentMethod::CREDIT_CARD]);
        }

        if ($status === BillingStatus::OVERDUE) {
            $days = (int) $due->diffInDays($ctx->now);
            $fine = (int) round($amount * 0.02);
            $interest = (int) round($amount * 0.01 * $days / 30);
        }

        $viaGateway = $method !== null ? $faker->numberBetween(1, 100) <= 75 : $status !== BillingStatus::CANCELLED;

        $billing = FinanceBilling::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'number' => $ctx->nextBillingNumber(),
            'subscription_id' => $subscription->getKey(),
            'client_id' => $subscription->client_id,
            'status' => $status,
            'payment_method' => $method,
            'payment_gateway' => $viaGateway ? 'asaas' : null,
            'amount_cents' => $amount,
            'discount_cents' => 0,
            'fine_cents' => $fine,
            'interest_cents' => $interest,
            'paid_amount_cents' => $paidAt !== null ? $amount + $fine + $interest : null,
            'due_at' => $due->toDateString(),
            'paid_at' => $paidAt,
            'cancelled_at' => $status === BillingStatus::CANCELLED ? $createdAt->addDays(1) : null,
            'gateway_payment_id' => $viaGateway ? 'pay_'.Str::lower(Str::random(16)) : null,
            'description' => $description ?? sprintf(
                'Cobrança %s — %s',
                $subscription->plan_name ?: 'Assinatura',
                $due->format('m/Y'),
            ),
            'created_at' => $createdAt,
            'updated_at' => $paidAt ?? $createdAt,
        ]);

        $this->event($ctx, $billing, 'created', null, BillingStatus::PENDING, $createdAt);

        if ($status === BillingStatus::OVERDUE || ($paidAt !== null && $paidAt->greaterThan($due->endOfDay()))) {
            $this->event($ctx, $billing, 'marked_overdue', BillingStatus::PENDING, BillingStatus::OVERDUE, $due->addDay()->setTime(3, 0));
        }

        if ($paidAt !== null) {
            $from = $paidAt->greaterThan($due->endOfDay()) ? BillingStatus::OVERDUE : BillingStatus::PENDING;
            $this->event($ctx, $billing, 'paid', $from, BillingStatus::PAID, $paidAt, ['paid_amount_cents' => $billing->paid_amount_cents]);
        }

        if ($status === BillingStatus::REFUNDED) {
            $this->event($ctx, $billing, 'refunded', BillingStatus::PAID, BillingStatus::REFUNDED, $paidAt->addDays(3), null, $finance);
        }

        if ($status === BillingStatus::CANCELLED) {
            $this->event($ctx, $billing, 'cancelled', BillingStatus::PENDING, BillingStatus::CANCELLED, $createdAt->addDays(1), null, $finance);
        }

        return $billing;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function event(
        DemoContext $ctx,
        FinanceBilling $billing,
        string $action,
        ?BillingStatus $from,
        ?BillingStatus $to,
        CarbonImmutable $at,
        ?array $meta = null,
        ?User $user = null,
    ): void {
        FinanceBillingEvent::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'billing_id' => $billing->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'meta' => $meta,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * @param  list<CarbonImmutable>  $dues
     */
    private function updateSubscriptionDates(FinanceSubscription $subscription, array $dues, ?FinanceBilling $last): void
    {
        if ($last === null || $dues === []) {
            return;
        }

        $lastDue = end($dues);
        $annual = $subscription->plan_periodicity === BillingPeriodicity::ANNUAL;

        $subscription->forceFill([
            'last_billed_at' => CarbonImmutable::parse($last->created_at)->toDateString(),
            'next_billing_at' => $subscription->cancelled_at !== null
                ? null
                : ($annual ? $lastDue->addYear() : $lastDue->addMonthNoOverflow())->toDateString(),
        ])->save();
    }

    private function suspendEquipments(DemoContext $ctx, int $clientId, CarbonImmutable $firstOverdue): void
    {
        $suspendedAt = $firstOverdue->addDays(6)->setTime(8, 0);

        foreach ($ctx->fleet as $entry) {
            if ((int) $entry['client']->getKey() !== $clientId || $entry['equipment'] === null) {
                continue;
            }

            Equipment::query()->whereKey($entry['equipment']->getKey())->update(['billing_suspended_at' => $suspendedAt]);
            $entry['equipment']->billing_suspended_at = $suspendedAt;
        }
    }
}
