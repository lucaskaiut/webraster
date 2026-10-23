<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Notification\Services\NotificationEngine;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;

/**
 * Notificações da OS (caixa de entrada + push no app do técnico).
 */
class ServiceOrderNotificationService
{
    public function __construct(private readonly NotificationEngine $engine) {}

    public function notifyAssigned(ServiceOrder $order): void
    {
        if ($order->technician_id === null) {
            return;
        }

        $this->send(
            [(int) $order->technician_id],
            $order,
            'service_order.assigned',
            'Nova OS atribuída',
            $this->describe($order, 'OS %s foi atribuída a você'),
        );
    }

    public function notifyUnassigned(ServiceOrder $order, int $technicianId): void
    {
        $this->send(
            [$technicianId],
            $order,
            'service_order.unassigned',
            'OS removida da sua agenda',
            sprintf('OS %s foi reatribuída a outro técnico.', $order->code),
        );
    }

    public function notifyRescheduled(ServiceOrder $order): void
    {
        if ($order->technician_id === null) {
            return;
        }

        $this->send(
            [(int) $order->technician_id],
            $order,
            'service_order.rescheduled',
            'Agendamento alterado',
            $this->describe($order, 'OS %s foi alterada'),
        );
    }

    public function notifyCancelled(ServiceOrder $order): void
    {
        $recipients = collect([$order->technician_id, $order->created_by])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->send(
            $recipients,
            $order,
            'service_order.cancelled',
            'OS cancelada',
            sprintf(
                'OS %s foi cancelada%s',
                $order->code,
                $order->cancellation_reason ? ': '.$order->cancellation_reason : '.',
            ),
        );
    }

    public function notifyReminder(ServiceOrder $order): void
    {
        if ($order->technician_id === null) {
            return;
        }

        $this->send(
            [(int) $order->technician_id],
            $order,
            'service_order.reminder',
            sprintf('Atendimento às %s', $this->localTime($order)),
            trim(sprintf(
                'OS %s · %s%s',
                $order->code,
                $order->client?->name ?? 'Cliente',
                $order->address ? ' · '.$order->address : '',
            )),
        );
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $extra
     */
    private function send(
        array $userIds,
        ServiceOrder $order,
        string $type,
        string $title,
        string $body,
        array $extra = [],
    ): void {
        if ($userIds === []) {
            return;
        }

        $recipients = User::query()->withoutGlobalScopes()->whereIn('id', $userIds)->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->send($recipients, new NotificationMessage(
            type: $type,
            title: $title,
            body: $body,
            data: array_merge([
                'source' => NotificationSource::SERVICE_ORDER->value,
                'service_order_id' => $order->uuid,
                'code' => $order->code,
                'status' => $order->status?->value,
                'scheduled_start_at' => $order->scheduled_start_at?->toIso8601String(),
            ], $extra),
            source: NotificationSource::SERVICE_ORDER,
        ));
    }

    private function describe(ServiceOrder $order, string $format): string
    {
        $text = sprintf($format, $order->code);

        if ($order->scheduled_start_at) {
            $text .= sprintf(' para %s às %s', $this->localDate($order), $this->localTime($order));
        }

        return $text.'.';
    }

    private function localDate(ServiceOrder $order): string
    {
        return $order->scheduled_start_at?->copy()->setTimezone(config('scheduling.timezone'))->format('d/m') ?? '';
    }

    private function localTime(ServiceOrder $order): string
    {
        return $order->scheduled_start_at?->copy()->setTimezone(config('scheduling.timezone'))->format('H:i') ?? '';
    }
}
