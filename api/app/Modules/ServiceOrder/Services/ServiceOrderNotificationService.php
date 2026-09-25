<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\Alert\Models\UserNotification;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;

class ServiceOrderNotificationService
{
    public function notifyAssigned(ServiceOrder $order): void
    {
        if ($order->technician_id === null) {
            return;
        }

        $this->notifyUser(
            (int) $order->technician_id,
            $order,
            'service_order.assigned',
            'OS atribuída',
            sprintf('%s foi atribuída a você.', $order->code),
        );
    }

    public function notifyStatusChanged(ServiceOrder $order, string $from, string $to): void
    {
        $recipients = collect([
            $order->technician_id,
            $order->created_by,
        ])->filter()->unique()->values();

        foreach ($recipients as $userId) {
            $this->notifyUser(
                (int) $userId,
                $order,
                'service_order.status_changed',
                'Status da OS atualizado',
                sprintf('%s: %s → %s', $order->code, $from, $to),
                ['from' => $from, 'to' => $to],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function notifyUser(
        int $userId,
        ServiceOrder $order,
        string $type,
        string $title,
        string $body,
        array $extra = [],
    ): void {
        $user = User::query()->withoutGlobalScopes()->find($userId);

        if ($user === null) {
            return;
        }

        $notification = new UserNotification;
        $notification->forceFill([
            'tenant_id' => $order->tenant_id,
            'user_id' => $userId,
            'alert_id' => null,
            'type' => $type,
            'source' => 'service_order',
            'title' => $title,
            'body' => $body,
            'data' => array_merge([
                'service_order_id' => $order->uuid,
                'code' => $order->code,
                'status' => $order->status?->value,
            ], $extra),
        ])->save();
    }
}
