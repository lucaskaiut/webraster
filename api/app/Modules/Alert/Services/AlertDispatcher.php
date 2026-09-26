<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertSeverity;
use App\Modules\Alert\Enums\AlertStatus;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Notifications\AlertMailNotification;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Notification\Services\NotificationEngine;
use App\Modules\Notification\Services\NotificationRecipientResolver;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AlertDispatcher
{
    public function __construct(
        private readonly NotificationEngine $engine,
        private readonly NotificationRecipientResolver $recipients,
        private readonly AlertConfigService $configs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        AlertConfig $config,
        AlertType $type,
        Vehicle $vehicle,
        array $payload,
        ?AlertSeverity $severity = null,
        bool $createWhenDisabled = false,
    ): ?Alert {
        if (! $config->is_enabled && ! $createWhenDisabled) {
            return null;
        }

        $alert = new Alert;
        $alert->forceFill([
            'tenant_id' => $vehicle->tenant_id,
            'client_id' => $vehicle->client_id,
            'vehicle_id' => $vehicle->getKey(),
            'equipment_id' => $payload['equipment_id'] ?? $vehicle->equipment?->getKey(),
            'gps_position_id' => $payload['gps_position_id'] ?? null,
            'type' => $type,
            'severity' => $severity ?? $type->defaultSeverity(),
            'status' => AlertStatus::OPEN,
            'title' => $payload['title'] ?? $type->label(),
            'description' => $payload['description'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'speed' => $payload['speed'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ])->save();

        $shouldNotify = $config->is_enabled
            && ($config->notify_in_app || $config->notify_push || $config->notify_email);

        $recipients = $shouldNotify ? $this->recipientsFor($alert, $vehicle) : collect();

        if ($config->is_enabled && ($config->notify_in_app || $config->notify_push)) {
            $this->notify($alert, $vehicle, (bool) $config->notify_push, $recipients);
        }

        if ($config->is_enabled && $config->notify_email) {
            $this->notifyEmail($alert, $vehicle, $recipients);
        }

        Log::info('alert.created', [
            'alert_id' => $alert->getKey(),
            'type' => $type->value,
            'vehicle_id' => $vehicle->getKey(),
            'tenant_id' => $vehicle->tenant_id,
        ]);

        return $alert;
    }

    /**
     * Usuários do tenant e do cliente do veículo. Quando o cliente silenciou
     * o alerta no portal, os usuários do cliente saem da lista — o operador
     * continua recebendo normalmente.
     *
     * @return Collection<int, User>
     */
    private function recipientsFor(Alert $alert, Vehicle $vehicle): Collection
    {
        $recipients = $this->recipients->forVehicle($vehicle);

        if ($this->configs->isClientSilenced($vehicle, $alert->type)) {
            $recipients = $recipients
                ->reject(fn (User $user) => $user->client_id !== null)
                ->values();
        }

        return $recipients;
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notify(Alert $alert, Vehicle $vehicle, bool $push, Collection $recipients): void
    {
        $this->engine->send($recipients, new NotificationMessage(
            type: $alert->type->value,
            title: $alert->title,
            body: $alert->description,
            data: [
                'alert_id' => $alert->uuid,
                'vehicle_id' => $vehicle->uuid,
                'plate' => $vehicle->plate,
                'severity' => $alert->severity->value,
                'latitude' => $alert->latitude,
                'longitude' => $alert->longitude,
            ],
            source: NotificationSource::ALERT,
            alertId: (int) $alert->getKey(),
            push: $push,
        ));
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notifyEmail(Alert $alert, Vehicle $vehicle, Collection $recipients): void
    {
        foreach ($recipients as $user) {
            try {
                $user->notify(new AlertMailNotification($alert, $vehicle));
            } catch (\Throwable $exception) {
                Log::warning('alert.email_failed', [
                    'user_id' => $user->getKey(),
                    'alert_id' => $alert->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
