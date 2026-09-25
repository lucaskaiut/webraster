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
    case DEVICE_ALARM = 'device_alarm';

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
            self::DEVICE_ALARM => 'Alarme do dispositivo',
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
            self::DEVICE_ALARM => AlertSeverity::HIGH,
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
            self::DEVICE_ALARM,
        ];
    }

    /**
     * Alertas que o cliente liga/desliga no portal (subconjunto simples).
     *
     * @return list<self>
     */
    public static function clientConfigurable(): array
    {
        return [
            self::IGNITION_ON,
            self::IGNITION_OFF,
            self::SOS,
            self::OFFLINE,
            self::BATTERY,
            self::DEVICE_ALARM,
        ];
    }

    public function description(): string
    {
        return match ($this) {
            self::SPEED => 'Avisa quando o veículo passa do limite de velocidade.',
            self::IGNITION_ON => 'Avisa quando o veículo é ligado.',
            self::IGNITION_OFF => 'Avisa quando o veículo é desligado.',
            self::SOS => 'Avisa quando o botão de SOS é acionado.',
            self::OFFLINE => 'Avisa quando o rastreador fica sem comunicação.',
            self::ONLINE => 'Avisa quando o rastreador volta a comunicar.',
            self::BATTERY => 'Avisa quando a bateria do rastreador fica baixa.',
            self::JAMMING => 'Avisa quando há tentativa de bloquear o sinal de GPS/GSM.',
            self::DEVICE_ALARM => 'Avisa alarmes do rastreador, como energia cortada, reboque ou porta aberta.',
        };
    }
}
