<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Finance\Services\FinanceSubscriptionService;
use App\Modules\Service\Models\Service;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientOrderService
{
    public function __construct(
        private readonly FinanceSubscriptionService $subscriptions,
    ) {}

    public function currentForClient(Client $client): ?ClientOrder
    {
        return ClientOrder::query()
            ->with(['client', 'items.service', 'items.vehicles', 'subscription'])
            ->where('client_id', $client->getKey())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Client $client, array $data): ClientOrder
    {
        $items = $this->resolveItems($client, $data['items'] ?? []);

        $dueDay = min(max((int) ($data['due_day'] ?? 10), 1), 28);
        $periodicity = BillingPeriodicity::from((string) ($data['periodicity'] ?? BillingPeriodicity::MONTHLY->value));
        $totalCents = collect($items)->sum(fn (array $item): int => $item['line_total_cents']);

        return DB::transaction(function () use ($client, $items, $dueDay, $periodicity, $totalCents, $data) {
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

            foreach ($order->items()->get() as $item) {
                $item->vehicles()->detach();
                $item->delete();
            }

            foreach ($items as $itemData) {
                $item = $order->items()->create([
                    'service_id' => $itemData['service_id'],
                    'service_name' => $itemData['service_name'],
                    'unit_amount_cents' => $itemData['unit_amount_cents'],
                    'quantity' => $itemData['quantity'],
                    'line_total_cents' => $itemData['line_total_cents'],
                ]);

                $item->vehicles()->sync($itemData['vehicle_ids']);
            }

            $subscription = $this->subscriptions->assignFromOrder($client, $order->fresh(['items.service']), [
                'due_day' => $dueDay,
                'next_billing_at' => $data['next_billing_at'] ?? null,
            ]);

            $order->forceFill(['finance_subscription_id' => $subscription->getKey()])->save();

            return $order->fresh(['client', 'items.service', 'items.vehicles', 'subscription']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rawItems
     * @return list<array{service_id: int, service_name: string, unit_amount_cents: int, quantity: int, line_total_cents: int, vehicle_ids: list<int>}>
     */
    private function resolveItems(Client $client, array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages([
                'items' => ['Inclua ao menos um serviço no pedido.'],
            ]);
        }

        $seenServices = [];
        $resolved = [];

        foreach ($rawItems as $index => $raw) {
            $serviceId = (int) ($raw['service_id'] ?? 0);
            $vehicleIds = array_values(array_unique(array_map('intval', $raw['vehicle_ids'] ?? [])));

            if (isset($seenServices[$serviceId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.service_id" => ['Cada serviço só pode aparecer uma vez no pedido.'],
                ]);
            }

            $seenServices[$serviceId] = true;

            $service = Service::query()->find($serviceId);

            if ($service === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.service_id" => ['Serviço inválido.'],
                ]);
            }

            if ($vehicleIds === []) {
                throw ValidationException::withMessages([
                    "items.{$index}.vehicle_ids" => ['Selecione ao menos um veículo para este serviço.'],
                ]);
            }

            $vehicles = Vehicle::query()
                ->where('client_id', $client->getKey())
                ->whereIn('id', $vehicleIds)
                ->get();

            if ($vehicles->count() !== count($vehicleIds)) {
                throw ValidationException::withMessages([
                    "items.{$index}.vehicle_ids" => ['Todos os veículos devem pertencer a este cliente.'],
                ]);
            }

            $quantity = $vehicles->count();
            $unitAmount = (int) $service->amount_cents;

            $resolved[] = [
                'service_id' => $service->getKey(),
                'service_name' => (string) $service->name,
                'unit_amount_cents' => $unitAmount,
                'quantity' => $quantity,
                'line_total_cents' => $unitAmount * $quantity,
                'vehicle_ids' => $vehicles->modelKeys(),
            ];
        }

        return $resolved;
    }
}
