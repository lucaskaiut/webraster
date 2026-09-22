<?php

namespace Tests\Unit\Tracking;

use App\Modules\Tracking\Support\TraccarBatteryLevel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TraccarBatteryLevelTest extends TestCase
{
    #[Test]
    public function identifies_protocols_that_report_battery_in_power(): void
    {
        $this->assertTrue(TraccarBatteryLevel::powerMeansBattery('easytrack'));
        $this->assertTrue(TraccarBatteryLevel::powerMeansBattery('EasyTrack'));
        $this->assertFalse(TraccarBatteryLevel::powerMeansBattery('teltonika'));
        $this->assertFalse(TraccarBatteryLevel::powerMeansBattery(null));
    }

    #[Test]
    public function normalizes_power_values_only_for_known_protocols(): void
    {
        $this->assertSame(94.0, TraccarBatteryLevel::fromPower('easytrack', 94));
        $this->assertNull(TraccarBatteryLevel::fromPower('teltonika', 94));
        $this->assertNull(TraccarBatteryLevel::fromPower('easytrack', 101));
        $this->assertNull(TraccarBatteryLevel::fromPower('easytrack', 12.6));
        $this->assertSame(24.0, TraccarBatteryLevel::fromPower('easytrack', 24));
    }
}
