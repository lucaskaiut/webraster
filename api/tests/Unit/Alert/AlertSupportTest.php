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

    #[Test]
    public function extracts_device_alarms_and_labels(): void
    {
        $this->assertSame('powercut', TraccarAttributeReader::extractDeviceAlarm(['alarm' => 'powerCut']));
        $this->assertSame('Alimentação cortada', TraccarAttributeReader::deviceAlarmLabel('powerCut'));
        $this->assertNull(TraccarAttributeReader::extractDeviceAlarm(['alarm' => 'sos']));
        $this->assertNull(TraccarAttributeReader::extractDeviceAlarm(['alarm' => 'powerRestored']));
    }

    #[Test]
    public function lists_active_alarms_for_monitoring(): void
    {
        $this->assertSame(['powercut'], TraccarAttributeReader::activeAlarms(['alarm' => 'powerCut']));
        $this->assertSame(['sos'], TraccarAttributeReader::activeAlarms(['sos' => true]));
        $this->assertSame([], TraccarAttributeReader::activeAlarms(['alarm' => 'powerRestored']));
    }

    #[Test]
    public function maps_restored_alarms_to_base_codes(): void
    {
        $this->assertTrue(TraccarAttributeReader::isRestoredAlarm('powerRestored'));
        $this->assertSame('powercut', TraccarAttributeReader::restoredBaseAlarm('powerRestored'));
    }

    #[Test]
    public function splits_comma_separated_traccar_alarms(): void
    {
        $this->assertSame(
            ['powercut', 'vibration'],
            TraccarAttributeReader::activeAlarms(['alarm' => 'powerCut, vibration']),
        );
        $this->assertSame(
            ['powercut', 'lowbattery'],
            TraccarAttributeReader::extractDeviceAlarms(['alarm' => 'powerCut, lowBattery']),
        );
    }

    #[Test]
    public function reads_telemetry_attributes(): void
    {
        $attributes = [
            'rssi' => 23,
            'sat' => 19,
            'adc1' => 14.01,
            'blocked' => false,
            'power' => 98,
        ];

        $this->assertSame(23.0, TraccarAttributeReader::signal($attributes));
        $this->assertSame(19, TraccarAttributeReader::satellites($attributes));
        $this->assertSame(14.01, TraccarAttributeReader::voltage($attributes));
        $this->assertFalse(TraccarAttributeReader::isBlocked($attributes));
    }

    #[Test]
    public function ignores_telemetry_values_that_are_not_applicable(): void
    {
        $this->assertNull(TraccarAttributeReader::signal([]));
        $this->assertNull(TraccarAttributeReader::satellites(null));
        $this->assertNull(TraccarAttributeReader::voltage(['power' => 98]));
        $this->assertNull(TraccarAttributeReader::isBlocked([]));
    }
}
