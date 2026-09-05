<?php

namespace App\Modules\Alert\Enums;

enum AlertType: string
{
    case SPEED = 'speed';
    case IGNITION_ON = 'ignition_on';
    case IGNITION_OFF = 'ignition_off';
    case SOS = 'sos';
    case OFFLINE = 'offline';
    case ONLINE = 'online';
    case BATTERY = 'battery';
    case JAMMING = 'jamming';

    public function label(): string
    {
        return match ($this) {
            self::SPEED => 'Excesso de velocidade',
            self::IGNITION_ON => 'Ignição ligada',
            self::IGNITION_OFF => 'Ignição desligada',
            self::SOS => 'SOS',
            self::OFFLINE => 'Dispositivo offline',
            self::ONLINE => 'Dispositivo online',
            self::BATTERY => 'Bateria baixa',
            self::JAMMING => 'Jamming',
        };
    }

    public function defaultSeverity(): AlertSeverity
    {
        return match ($this) {
            self::SPEED => AlertSeverity::MEDIUM,
            self::IGNITION_ON, self::IGNITION_OFF => AlertSeverity::LOW,
            self::BATTERY => AlertSeverity::MEDIUM,
            self::OFFLINE, self::ONLINE => AlertSeverity::HIGH,
            self::SOS, self::JAMMING => AlertSeverity::CRITICAL,
        };
    }

    /**
     * @return list<self>
     */
    public static function configurable(): array
    {
        return [
            self::SPEED,
            self::IGNITION_ON,
            self::IGNITION_OFF,
            self::SOS,
            self::OFFLINE,
            self::BATTERY,
            self::JAMMING,
        ];
    }
}
