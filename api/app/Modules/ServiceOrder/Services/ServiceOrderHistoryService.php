<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderHistory;
use App\Modules\User\Models\User;

class ServiceOrderHistoryService
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        ServiceOrder $order,
        ServiceOrderHistoryAction $action,
        ?User $user = null,
        ?string $field = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?array $meta = null,
    ): ServiceOrderHistory {
        $history = new ServiceOrderHistory;
        $history->forceFill([
            'tenant_id' => $order->tenant_id,
            'service_order_id' => $order->getKey(),
            'user_id' => $user?->getKey(),
            'action' => $action,
            'field' => $field,
            'old_value' => $this->stringify($oldValue),
            'new_value' => $this->stringify($newValue),
            'meta' => $meta,
        ])->save();

        return $history;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
