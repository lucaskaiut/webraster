<?php

namespace Tests\Unit\Alert;

use App\Modules\Alert\Support\SpeedExcessSettings;
use App\Modules\Vehicle\Models\Vehicle;
use PHPUnit\Framework\TestCase;

class SpeedExcessSettingsTest extends TestCase
{
    public function test_thresholds_use_hysteresis_percent(): void
    {
        $vehicle = new Vehicle([
            'max_speed_kmh' => 80,
            'speed_hysteresis_percent' => 3,
            'speed_min_duration_seconds' => 30,
        ]);

        $settings = SpeedExcessSettings::forVehicle($vehicle);

        $this->assertNotNull($settings);
        $this->assertSame(82.4, $settings->openThreshold());
        $this->assertSame(77.6, $settings->closeThreshold());
    }

    public function test_returns_null_without_limit(): void
    {
        $vehicle = new Vehicle([
            'max_speed_kmh' => null,
        ]);

        $this->assertNull(SpeedExcessSettings::forVehicle($vehicle));
    }
}
