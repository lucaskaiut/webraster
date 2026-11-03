<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Client\Models\ClientOrderItem;
use App\Modules\Finance\Enums\PlanPricingType;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Services\FinanceBillingService;
use App\Modules\Finance\Services\FinanceSubscriptionService;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientOrderService
{
    /**
     * Quantidade de cobranças geradas de imediato quando o pedido é criado.
     */
    private const INITIAL_BILLINGS = 2;

    public function __construct(
        private readonly FinanceSubscriptionService $subscriptions,
        private readonly FinanceBillingService $billings,
    ) {}

    public function currentForClient(Client $client): ?ClientOrder
    {
        return ClientOrder::query()
            ->with(['client', 'items.plan', 'items.service', 'items.vehicles', 'subscription'])
            ->where('client_id', $client->getKey())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Client $client, array $data): ClientOrder
    {
        $item = $this->resolvePlanItem($client, $data);

        $dueDay = min(max((int) ($data['due_day'] ?? 10), 1), 28);
        $periodicity = $item['periodicity'];
        $totalCents = $item['line_total_cents'];

        return DB::transaction(function () use ($client, $item, $dueDay, $periodicity, $totalCents, $data) {
            $order = ClientOrder::withTrashed()
                ->where('client_id', $client->getKey())
                ->first();

            if ($order === null) {
                $order = new ClientOrder;
                $order->client_id = $client->getKey();
            }

            if ($order->trashed()) {
                $order->restore();
            }

            $order->fill([
                'due_day' => $dueDay,
                'periodicity' => $periodicity,
                'total_cents' => $totalCents,
            ]);
            $order->save();

            foreach ($order->items()->get() as $existing) {
                $existing->vehicles()->detach();
                $existing->delete();
            }

            $created = $order->items()->create([
                'service_id' => null,
                'plan_id' => $item['plan_id'],
                'service_name' => $item['name'],
                'pricing_type' => $item['pricing_type'],
                'unit_amount_cents' => $item['unit_amount_cents'],
                'quantity' => $item['quantity'],
                'line_total_cents' => $item['line_total_cents'],
            ]);

            $created->vehicles()->sync($item['vehicle_ids']);

            $isNewSubscription = $this->subscriptions->currentForClient($client) === null;

            $subscription = $this->subscriptions->assignFromOrder($client, $order->fresh(['items']), [
                'due_day' => $dueDay,
                'next_billing_at' => $data['next_billing_at'] ?? null,
            ]);

            if ($isNewSubscription) {
                $this->billings->generateInitialForSubscription($subscription, self::INITIAL_BILLINGS);
                $this->subscriptions->advanceBillingDates($subscription, self::INITIAL_BILLINGS);
            }

            $order->forceFill(['finance_subscription_id' => $subscription->getKey()])->save();

            return $order->fresh(['client', 'items.plan', 'items.vehicles', 'subscription']);
        });
    }

    /**
     * Resolve o plano do pedido e calcula o valor recorrente: valor fixo do
     * plano, ou valor do plano × veículos selecionados quando por veículo.
     *
     * @param  array<string, mixed>  $data
     * @return array{plan_id: int, name: string, pricing_type: PlanPricingType, periodicity: BillingPeriodicity, unit_amount_cents: int, quantity: int, line_total_cents: int, vehicle_ids: list<int>}
     */
    private function resolvePlanItem(Client $client, array $data): array
    {
        $plan = FinancePlan::query()->find((int) ($data['plan_id'] ?? 0));

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['Plano inválido.'],
            ]);
        }

        if (! $plan->is_active && ! $this->isCurrentPlan($client, $plan)) {
            throw ValidationException::withMessages([
                'plan_id' => ['O plano selecionado está inativo.'],
            ]);
        }

        $vehicleIds = [];
        $quantity = 1;

        if ($plan->isPerVehicle()) {
            $requested = array_values(array_unique(array_map('intval', $data['vehicle_ids'] ?? [])));

            if ($requested === []) {
                throw ValidationException::withMessages([
                    'vehicle_ids' => ['Selecione ao menos um veículo para este plano.'],
                ]);
            }

            $vehicles = Vehicle::query()
                ->where('client_id', $client->getKey())
                ->whereIn('id', $requested)
                ->get();

            if ($vehicles->count() !== count($requested)) {
                throw ValidationException::withMessages([
                    'vehicle_ids' => ['Todos os veículos devem pertencer a este cliente.'],
                ]);
            }

            $vehicleIds = $vehicles->modelKeys();
            $quantity = count($vehicleIds);
        }

        return [
            'plan_id' => $plan->getKey(),
            'name' => (string) $plan->name,
            'pricing_type' => $plan->pricing_type ?? PlanPricingType::FIXED,
            'periodicity' => $plan->periodicity ?? BillingPeriodicity::MONTHLY,
            'unit_amount_cents' => (int) $plan->amount_cents,
            'quantity' => $quantity,
            'line_total_cents' => $plan->priceFor($quantity),
            'vehicle_ids' => $vehicleIds,
        ];
    }

    private function isCurrentPlan(Client $client, FinancePlan $plan): bool
    {
        return ClientOrderItem::query()
            ->where('plan_id', $plan->getKey())
            ->whereHas('order', fn ($query) => $query->where('client_id', $client->getKey()))
            ->exists();
    }
}
