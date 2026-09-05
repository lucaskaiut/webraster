<?php

namespace App\Modules\Alert\Services\Rules;

use App\Modules\Alert\Models\Alert;
use App\Modules\Tracking\Models\GpsPosition;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Support\Collection;

interface AlertRule
{
    /**
     * @return Collection<int, Alert>
     */
    public function evaluate(Vehicle $vehicle, GpsPosition $position): Collection;
}
