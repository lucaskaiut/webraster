<?php

namespace App\Modules\Commission\Services;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Commission\Enums\CommissionStatus;
use App\Modules\Commission\Models\Commission;
use App\Modules\Service\Enums\CommissionType;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use App\Modules\Shared\Support\QuerySort;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CommissionService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = [], ?User $user = null): LengthAwarePaginator
    {
        $query = Commission::query()
            ->with(['serviceOrder.client', 'technician'])
            ->whereHas('serviceOrder')
            ->whereIn('status', array_map(
                fn (CommissionStatus $status) => $status->value,
                CommissionStatus::visible(),
            ));

        if ($user !== null && ! $user->hasPermission(Permission::COMMISSION_VIEW_OTHERS)) {
            $query->where('technician_id', $user->getKey());
        } elseif (! empty($filters['technician_id'])) {
            $query->where('technician_id', $filters['technician_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $number = null;

            if (preg_match('/^OS-?0*(\d+)$/i', $search, $matches)) {
                $number = (int) $matches[1];
            } elseif (ctype_digit($search)) {
                $number = (int) $search;
            }

            $query->where(function (Builder $query) use ($search, $number): void {
                $query->whereHas('serviceOrder', function (Builder $query) use ($search, $number): void {
                    $query->where('description', 'like', "%{$search}%");

                    if ($number !== null) {
                        $query->orWhere('number', $number);
                    }
                })
                    ->orWhereHas('serviceOrder.client', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('technician', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"));
            });
        }

        return QuerySort::apply(
            $query,
            $filters['sort'] ?? null,
            ['status', 'base_amount_cents', 'amount_cents', 'finalized_at', 'created_at'],
            'created_at:desc',
        )->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Cria ou atualiza o snapshot de comissão de uma OS.
     */
    public function syncForOrder(ServiceOrder $order): Commission
    {
        $order->load(['items.service' => fn ($query) => $query->withTrashed()]);

        $status = CommissionStatus::fromOrderStatus(
            $order->status instanceof ServiceOrderStatus
                ? $order->status
                : ServiceOrderStatus::from((string) $order->status),
        );

        $existing = Commission::query()
            ->where('service_order_id', $order->getKey())
            ->first();

        $baseAmountCents = 0;
        $amountCents = 0;
        $breakdown = [];

        foreach ($order->items as $item) {
            $baseAmount = $this->itemBaseAmountCents($item);
            $commissionAmount = $this->itemCommissionAmountCents($item);

            $baseAmountCents += $baseAmount;
            $amountCents += $commissionAmount;

            $breakdown[] = [
                'service_id' => $item->service?->uuid,
                'service_name' => $item->name,
                'quantity' => $item->quantity,
                'base_amount_cents' => $baseAmount,
                'commission_type' => $this->itemCommissionType($item)?->value,
                'commission_value' => $this->itemCommissionValue($item),
                'amount_cents' => $commissionAmount,
            ];
        }

        $commission = $existing ?? new Commission([
            'tenant_id' => $order->tenant_id,
            'service_order_id' => $order->getKey(),
        ]);

        $commission->fill([
            'technician_id' => $order->technician_id,
            'status' => $status,
            'base_amount_cents' => $baseAmountCents,
            'amount_cents' => $amountCents,
            'breakdown' => $breakdown,
            'finalized_at' => $status === CommissionStatus::FINALIZED
                ? ($existing?->finalized_at ?? now())
                : null,
        ]);

        $commission->save();

        return $commission;
    }

    private function itemBaseAmountCents(ServiceOrderItem $item): int
    {
        return max(0, (int) $item->quantity * (int) $item->unit_amount_cents);
    }

    private function itemCommissionAmountCents(ServiceOrderItem $item): int
    {
        return match ($this->itemCommissionType($item)) {
            CommissionType::PERCENTAGE => (int) round(
                $this->itemBaseAmountCents($item) * $this->itemCommissionValue($item) / 10000,
            ),
            CommissionType::FIXED => $this->itemCommissionValue($item) * max(1, (int) $item->quantity),
            default => 0,
        };
    }

    private function itemCommissionType(ServiceOrderItem $item): ?CommissionType
    {
        // Itens antigos (ou criados fora do fluxo da OS) usam o cadastro atual do serviço.
        return $item->commission_type ?? $item->service?->commission_type;
    }

    private function itemCommissionValue(ServiceOrderItem $item): int
    {
        return $item->commission_type !== null
            ? (int) $item->commission_value
            : (int) ($item->service?->commission_value ?? 0);
    }
}
