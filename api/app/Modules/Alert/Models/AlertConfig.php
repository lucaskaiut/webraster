<?php

namespace App\Modules\Alert\Models;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Client\Models\Client;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertConfig extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'client_id',
        'vehicle_id',
        'name',
        'type',
        'is_enabled',
        'notify_in_app',
        'notify_email',
        'notify_push',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'is_enabled' => 'boolean',
            'notify_in_app' => 'boolean',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsWithDefaults(): array
    {
        $defaults = match ($this->type) {
            AlertType::SPEED => [
                'speed_limit_kmh' => 80,
                'min_duration_seconds' => 30,
            ],
            AlertType::OFFLINE => [
                'offline_minutes' => 15,
            ],
            AlertType::BATTERY => [
                'battery_threshold' => 20,
            ],
            default => [],
        };

        return array_merge($defaults, is_array($this->settings) ? $this->settings : []);
    }

    public function appliesToVehicle(Vehicle $vehicle): bool
    {
        if ((int) $this->tenant_id !== (int) $vehicle->tenant_id) {
            return false;
        }

        if ($this->vehicle_id !== null) {
            return (int) $this->vehicle_id === (int) $vehicle->getKey();
        }

        if ($this->client_id !== null) {
            return (int) $this->client_id === (int) $vehicle->client_id;
        }

        return true;
    }
}
