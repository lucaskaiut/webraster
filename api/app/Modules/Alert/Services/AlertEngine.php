<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Services\Rules\AlertRule;
use App\Modules\Alert\Services\Rules\BatteryAlertRule;
use App\Modules\Alert\Services\Rules\IgnitionAlertRule;
use App\Modules\Alert\Services\Rules\JammingAlertRule;
use App\Modules\Alert\Services\Rules\SosAlertRule;
use App\Modules\Alert\Services\Rules\SpeedAlertRule;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AlertEngine
{
    /** @var list<AlertRule> */
    private array $rules;

    public function __construct(
        SpeedAlertRule $speed,
        IgnitionAlertRule $ignition,
        SosAlertRule $sos,
        BatteryAlertRule $battery,
        JammingAlertRule $jamming,
    ) {
        $this->rules = [$speed, $ignition, $sos, $battery, $jamming];
    }

    /**
     * @return Collection<int, \App\Modules\Alert\Models\Alert>
     */
    public function process(GpsPosition $position): Collection
    {
        $vehicle = Vehicle::query()
            ->withoutGlobalScopes()
            ->with('equipment')
            ->find($position->vehicle_id);

        if ($vehicle === null || ! $vehicle->is_active) {
            return collect();
        }

        $alerts = collect();

        foreach ($this->rules as $rule) {
            try {
                $alerts = $alerts->merge($rule->evaluate($vehicle, $position));
            } catch (\Throwable $exception) {
                Log::warning('alert.rule_failed', [
                    'rule' => $rule::class,
                    'position_id' => $position->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        // Posição recebida encerra estado offline (gera ONLINE se necessário).
        try {
            $alerts = $alerts->merge(app(OfflineAlertService::class)->markOnline($vehicle, $position));
        } catch (\Throwable $exception) {
            Log::warning('alert.online_failed', [
                'vehicle_id' => $vehicle->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }

        return $alerts;
    }
}
