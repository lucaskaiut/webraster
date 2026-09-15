<?php

namespace App\Modules\Alert\Services;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Alert\Enums\AlertSeverity;
use App\Modules\Alert\Enums\AlertStatus;
use App\Modules\Alert\Enums\AlertType;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Models\UserNotification;
use App\Modules\Alert\Notifications\AlertMailNotification;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Facades\Log;

class AlertDispatcher
{
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

        if ($config->is_enabled && $config->notify_in_app) {
            $this->notifyInApp($alert, $vehicle);
        }

        if ($config->is_enabled && $config->notify_email) {
            $this->notifyEmail($alert, $vehicle);
        }

        Log::info('alert.created', [
            'alert_id' => $alert->getKey(),
            'type' => $type->value,
            'vehicle_id' => $vehicle->getKey(),
            'tenant_id' => $vehicle->tenant_id,
        ]);

        return $alert;
    }

    private function recipients(Vehicle $vehicle)
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $vehicle->tenant_id)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($vehicle): void {
                $query->whereNull('client_id')
                    ->orWhere('client_id', $vehicle->client_id);
            })
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::ALERT_READ)
                || $user->hasPermission(Permission::NOTIFICATION_READ));
    }

    private function notifyInApp(Alert $alert, Vehicle $vehicle): void
    {
        foreach ($this->recipients($vehicle) as $user) {
            $notification = new UserNotification;
            $notification->forceFill([
                'tenant_id' => $alert->tenant_id,
                'user_id' => $user->getKey(),
                'alert_id' => $alert->getKey(),
                'type' => $alert->type->value,
                'title' => $alert->title,
                'body' => $alert->description,
                'data' => [
                    'alert_id' => $alert->uuid,
                    'vehicle_id' => $vehicle->uuid,
                    'plate' => $vehicle->plate,
                    'severity' => $alert->severity->value,
                    'latitude' => $alert->latitude,
                    'longitude' => $alert->longitude,
                ],
            ])->save();
        }
    }

    private function notifyEmail(Alert $alert, Vehicle $vehicle): void
    {
        foreach ($this->recipients($vehicle) as $user) {
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
