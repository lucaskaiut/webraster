<?php

namespace App\Modules\Alert\Support;

use App\Modules\Alert\Enums\AlertSeverity;
use App\Modules\Tracking\Support\TraccarBatteryLevel;

/**
 * Extrai indicadores de alarmes Traccar dos attributes quando presentes.
 * Não inventa heurísticas — só interpreta chaves conhecidas do protocolo.
 */
final class TraccarAttributeReader
{
    /** Tensão mínima (V) para considerar alimentação externa conectada. */
    private const EXTERNAL_POWER_MIN_VOLTAGE = 10.0;

    /** Alarmes tratados por regras dedicadas (SOS, jamming, velocidade). */
    private const EXCLUDED_DEVICE_ALARMS = [
        'sos',
        'emergency',
        'panic',
        'jamming',
        'gpsjamming',
        'gsmjamming',
        'overspeed',
    ];

    /** @var array<string, string> */
    private const RESTORED_TO_BASE = [
        'powerrestored' => 'powercut',
        'poweron' => 'poweroff',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'general' => 'Alarme geral',
        'sos' => 'SOS',
        'vibration' => 'Vibração',
        'movement' => 'Movimento',
        'overspeed' => 'Excesso de velocidade',
        'falldown' => 'Queda',
        'lowpower' => 'Tensão baixa',
        'lowbattery' => 'Bateria baixa',
        'fault' => 'Falha',
        'poweroff' => 'Dispositivo desligado',
        'poweron' => 'Dispositivo ligado',
        'door' => 'Porta',
        'lock' => 'Travamento',
        'unlock' => 'Destravamento',
        'geofence' => 'Geocerca',
        'geofenceenter' => 'Entrada em geocerca',
        'geofenceexit' => 'Saída de geocerca',
        'gpsantennacut' => 'Antena GPS cortada',
        'accident' => 'Acidente',
        'tow' => 'Reboque',
        'idle' => 'Ocioso',
        'highrpm' => 'RPM alto',
        'hardacceleration' => 'Aceleração brusca',
        'hardbraking' => 'Frenagem brusca',
        'hardcornering' => 'Curva brusca',
        'lanechange' => 'Mudança de faixa',
        'fatiguedriving' => 'Fadiga ao volante',
        'powercut' => 'Alimentação cortada',
        'powerrestored' => 'Alimentação restaurada',
        'jamming' => 'Jamming',
        'temperature' => 'Temperatura',
        'parking' => 'Estacionamento',
        'bonnet' => 'Capô',
        'footbrake' => 'Freio de pé',
        'fuelleak' => 'Vazamento de combustível',
        'tampering' => 'Violação',
        'removing' => 'Remoção',
    ];

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public static function isSos(?array $attributes): bool
    {
        if ($attributes === null || $attributes === []) {
            return false;
        }

        if (array_key_exists('sos', $attributes) && filter_var($attributes['sos'], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        foreach (self::parseAlarmCodes($attributes['alarm'] ?? null) as $alarm) {
            if (in_array($alarm, ['sos', 'emergency', 'panic'], true) || str_contains($alarm, 'sos')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public static function isJamming(?array $attributes): bool
    {
        if ($attributes === null || $attributes === []) {
            return false;
        }

        foreach (['jamming', 'gpsJamming', 'gsmJamming', 'signalJamming'] as $key) {
            if (array_key_exists($key, $attributes) && filter_var($attributes[$key], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        foreach (self::parseAlarmCodes($attributes['alarm'] ?? null) as $alarm) {
            if (in_array($alarm, ['jamming', 'gpsjamming', 'gsmjamming'], true) || str_contains($alarm, 'jam')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alarmes ativos para exibição no monitoramento (inclui SOS/jamming).
     *
     * @param  array<string, mixed>|null  $attributes
     * @return list<string>
     */
    public static function activeAlarms(?array $attributes): array
    {
        if ($attributes === null || $attributes === []) {
            return [];
        }

        $codes = [];

        foreach (self::parseAlarmCodes($attributes['alarm'] ?? null) as $alarm) {
            if (! self::isRestoredAlarm($alarm)) {
                $codes[] = $alarm;
            }
        }

        if (self::isSos($attributes) && ! in_array('sos', $codes, true)) {
            $codes[] = 'sos';
        }

        if (self::isJamming($attributes) && ! in_array('jamming', $codes, true)) {
            $codes[] = 'jamming';
        }

        return array_values(array_unique($codes));
    }

    /**
     * Alarmes de dispositivo para o motor de alertas (exclui SOS/jamming/overspeed).
     *
     * @param  array<string, mixed>|null  $attributes
     */
    /**
     * @param  array<string, mixed>|null  $attributes
     * @return list<string>
     */
    public static function extractDeviceAlarms(?array $attributes): array
    {
        if ($attributes === null || $attributes === []) {
            return [];
        }

        return array_values(array_filter(
            self::parseAlarmCodes($attributes['alarm'] ?? null),
            fn (string $alarm) => ! self::isRestoredAlarm($alarm) && ! self::isExcludedDeviceAlarm($alarm),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public static function extractDeviceAlarm(?array $attributes): ?string
    {
        return self::extractDeviceAlarms($attributes)[0] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     * @return list<string>
     */
    public static function extractRestoredAlarms(?array $attributes): array
    {
        if ($attributes === null || $attributes === []) {
            return [];
        }

        return array_values(array_filter(
            self::parseAlarmCodes($attributes['alarm'] ?? null),
            fn (string $alarm) => self::isRestoredAlarm($alarm),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public static function extractRestoredAlarm(?array $attributes): ?string
    {
        return self::extractRestoredAlarms($attributes)[0] ?? null;
    }

    public static function isRestoredAlarm(string $alarm): bool
    {
        $normalized = self::normalizeAlarmCode($alarm);

        if ($normalized === '') {
            return false;
        }

        if (isset(self::RESTORED_TO_BASE[$normalized])) {
            return true;
        }

        return str_ends_with($normalized, 'restored')
            || str_ends_with($normalized, 'exit')
            || in_array($normalized, ['poweron', 'alarmdisarm'], true);
    }

    public static function restoredBaseAlarm(string $restoredAlarm): ?string
    {
        $normalized = self::normalizeAlarmCode($restoredAlarm);

        if (isset(self::RESTORED_TO_BASE[$normalized])) {
            return self::RESTORED_TO_BASE[$normalized];
        }

        if (str_ends_with($normalized, 'restored')) {
            return substr($normalized, 0, -strlen('restored'));
        }

        if (str_ends_with($normalized, 'exit')) {
            return substr($normalized, 0, -strlen('exit'));
        }

        return null;
    }

    public static function deviceAlarmLabel(string $code): string
    {
        $normalized = self::normalizeAlarmCode($code);

        if (isset(self::LABELS[$normalized])) {
            return self::LABELS[$normalized];
        }

        return ucfirst(str_replace('_', ' ', $normalized));
    }

    /**
     * Alarmes de dispositivo configuráveis individualmente, ordenados por
     * severidade (críticos primeiro). Exclui SOS/jamming/velocidade, que têm
     * tipos dedicados, e alarmes de restauração.
     *
     * @return list<array{code: string, label: string, severity: string}>
     */
    public static function configurableDeviceAlarms(): array
    {
        $alarms = [];

        foreach (self::LABELS as $code => $label) {
            if (self::isExcludedDeviceAlarm($code) || self::isRestoredAlarm($code)) {
                continue;
            }

            $alarms[] = [
                'code' => $code,
                'label' => $label,
                'severity' => self::deviceAlarmSeverity($code)->value,
            ];
        }

        usort($alarms, function (array $a, array $b): int {
            return [self::severityRank($a['severity']), $a['label']]
                <=> [self::severityRank($b['severity']), $b['label']];
        });

        return array_values($alarms);
    }

    private static function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 0,
            'high' => 1,
            'medium' => 2,
            default => 3,
        };
    }

    public static function deviceAlarmSeverity(string $code): AlertSeverity
    {
        $normalized = self::normalizeAlarmCode($code);

        return match (true) {
            in_array($normalized, ['powercut', 'tampering', 'removing', 'accident', 'sos'], true) => AlertSeverity::CRITICAL,
            in_array($normalized, ['lowbattery', 'lowpower', 'gpsantennacut', 'fuelleak', 'fault'], true) => AlertSeverity::HIGH,
            default => AlertSeverity::MEDIUM,
        };
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     * @return list<array{code: string, label: string, severity: string}>
     */
    public static function formatAlarmsForApi(?array $attributes): array
    {
        return array_map(
            fn (string $code) => [
                'code' => $code,
                'label' => self::deviceAlarmLabel($code),
                'severity' => self::deviceAlarmSeverity($code)->value,
            ],
            self::activeAlarms($attributes),
        );
    }

    /**
     * Normaliza bateria para percentual quando possível.
     * Valores > 100 são tratados como tensão (ignorados para alerta percentual).
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function batteryPercent(?float $battery, ?array $attributes = null): ?float
    {
        $value = $battery;

        if ($value === null && is_array($attributes)) {
            foreach (['batteryLevel', 'battery'] as $key) {
                if (isset($attributes[$key]) && is_numeric($attributes[$key])) {
                    $value = (float) $attributes[$key];

                    break;
                }
            }
        }

        if ($value === null && is_array($attributes)
            && isset($attributes['power']) && is_numeric($attributes['power'])) {
            $protocol = isset($attributes['protocol']) && is_string($attributes['protocol'])
                ? $attributes['protocol']
                : null;
            $value = TraccarBatteryLevel::fromPower($protocol, (float) $attributes['power']);
        }

        if ($value === null) {
            return null;
        }

        // Valores típicos de tensão (ex.: 12.6V) não são percentual.
        if ($value > 100 || ($value < 30 && fmod($value, 1.0) !== 0.0)) {
            return null;
        }

        if ($value < 0) {
            return null;
        }

        return $value;
    }

    /**
     * Intensidade de sinal GSM bruta reportada pelo protocolo (escala varia:
     * dBm negativo, 0-5 barras, 0-31 CSQ ou percentual).
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function signal(?array $attributes): ?float
    {
        if ($attributes === null) {
            return null;
        }

        foreach (['rssi', 'signal', 'gsmSignal', 'signalStrength'] as $key) {
            if (isset($attributes[$key]) && is_numeric($attributes[$key])) {
                return (float) $attributes[$key];
            }
        }

        return null;
    }

    /**
     * Quantidade de satélites fixados pelo GPS, quando o protocolo informa.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function satellites(?array $attributes): ?int
    {
        if ($attributes === null) {
            return null;
        }

        foreach (['sat', 'satellites'] as $key) {
            if (isset($attributes[$key]) && is_numeric($attributes[$key])) {
                return (int) $attributes[$key];
            }
        }

        return null;
    }

    /**
     * Tensão da alimentação externa, quando o protocolo informa um valor
     * compatível com volts (6V a 30V). Evita confundir percentual com tensão.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function voltage(?array $attributes): ?float
    {
        if ($attributes === null) {
            return null;
        }

        foreach (['adc1', 'externalPower', 'power', 'voltage', 'batteryVoltage'] as $key) {
            if (! isset($attributes[$key]) || ! is_numeric($attributes[$key])) {
                continue;
            }

            $value = (float) $attributes[$key];

            if ($value >= 6.0 && $value <= 30.0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Alimentação externa inferida quando o protocolo não envia um booleano.
     * Chaves dedicadas (adc1 etc.) permitem detectar o corte mesmo abaixo de
     * 6V; como fallback usa a tensão já normalizada (6V a 30V).
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function externalPower(?array $attributes): ?bool
    {
        if ($attributes === null) {
            return null;
        }

        if (array_key_exists('externalPower', $attributes) && is_bool($attributes['externalPower'])) {
            return $attributes['externalPower'];
        }

        foreach (['adc1', 'externalPower', 'batteryVoltage'] as $key) {
            if (isset($attributes[$key]) && is_numeric($attributes[$key])) {
                return (float) $attributes[$key] >= self::EXTERNAL_POWER_MIN_VOLTAGE;
            }
        }

        $voltage = self::voltage($attributes);

        return $voltage !== null ? $voltage >= self::EXTERNAL_POWER_MIN_VOLTAGE : null;
    }

    /**
     * Bloqueio veicular reportado pelo dispositivo. Null quando o protocolo
     * não informa o atributo.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public static function isBlocked(?array $attributes): ?bool
    {
        if ($attributes === null || ! array_key_exists('blocked', $attributes)) {
            return null;
        }

        return filter_var($attributes['blocked'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Traccar pode enviar vários alarmes no mesmo atributo, separados por vírgula.
     *
     * @return list<string>
     */
    public static function parseAlarmCodes(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', (string) $value) ?: [];
        $codes = [];

        foreach ($parts as $part) {
            $code = self::normalizeAlarmCode($part);

            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function normalizeAlarmCode(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtolower(trim((string) $value));
    }

    private static function isExcludedDeviceAlarm(string $alarm): bool
    {
        $normalized = self::normalizeAlarmCode($alarm);

        return in_array($normalized, self::EXCLUDED_DEVICE_ALARMS, true);
    }
}
