<?php

namespace Tests\Unit\Tracking;

use App\Modules\Tracking\DTOs\TraccarPosition;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TraccarPositionTest extends TestCase
{
    #[Test]
    public function maps_power_as_battery_for_known_protocols(): void
    {
        $position = TraccarPosition::fromArray($this->payload(['power' => 94]));

        $this->assertSame(94.0, $position->battery);
    }

    #[Test]
    public function keeps_battery_attributes_priority_over_power(): void
    {
        $position = TraccarPosition::fromArray($this->payload(['battery' => 80, 'power' => 94]));

        $this->assertSame(80.0, $position->battery);
    }

    #[Test]
    public function ignores_power_voltage_values_for_battery(): void
    {
        $position = TraccarPosition::fromArray($this->payload(['power' => 12.6]));

        $this->assertNull($position->battery);
    }

    #[Test]
    public function ignores_power_for_protocols_that_report_voltage(): void
    {
        $position = TraccarPosition::fromArray($this->payload(['power' => 94], protocol: 'teltonika'));

        $this->assertNull($position->battery);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes, string $protocol = 'easytrack'): array
    {
        return [
            'deviceId' => 183,
            'protocol' => $protocol,
            'latitude' => -25.54609,
            'longitude' => -49.17535,
            'deviceTime' => '2026-09-22 17:37:17',
            'attributes' => $attributes,
        ];
    }
}
