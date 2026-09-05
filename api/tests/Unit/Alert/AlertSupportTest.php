<?php

namespace Tests\Unit\Alert;

use App\Modules\Alert\Support\SpeedConverter;
use App\Modules\Alert\Support\TraccarAttributeReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertSupportTest extends TestCase
{
    #[Test]
    public function converts_knots_to_kmh(): void
    {
        $this->assertSame(80.0, SpeedConverter::knotsToKmh(80 / 1.852));
        $this->assertNull(SpeedConverter::knotsToKmh(null));
    }

    #[Test]
    public function detects_sos_from_attributes(): void
    {
        $this->assertTrue(TraccarAttributeReader::isSos(['sos' => true]));
        $this->assertTrue(TraccarAttributeReader::isSos(['alarm' => 'sos']));
        $this->assertFalse(TraccarAttributeReader::isSos(['alarm' => 'overspeed']));
        $this->assertFalse(TraccarAttributeReader::isSos([]));
    }

    #[Test]
    public function detects_jamming_only_when_protocol_says_so(): void
    {
        $this->assertTrue(TraccarAttributeReader::isJamming(['jamming' => true]));
        $this->assertTrue(TraccarAttributeReader::isJamming(['alarm' => 'jamming']));
        $this->assertFalse(TraccarAttributeReader::isJamming(['alarm' => 'powerCut']));
        $this->assertFalse(TraccarAttributeReader::isJamming(null));
    }

    #[Test]
    public function battery_percent_ignores_voltage_values(): void
    {
        $this->assertSame(15.0, TraccarAttributeReader::batteryPercent(15));
        $this->assertNull(TraccarAttributeReader::batteryPercent(12.6));
        $this->assertSame(10.0, TraccarAttributeReader::batteryPercent(null, ['batteryLevel' => 10]));
    }
}
