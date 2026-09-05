<?php

namespace App\Modules\Alert\Support;

/**
 * Extrai indicadores SOS/jamming dos attributes Traccar quando presentes.
 * Não inventa heurísticas — só interpreta chaves conhecidas do protocolo.
 */
final class TraccarAttributeReader
{
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

        $alarm = strtolower((string) ($attributes['alarm'] ?? ''));

        return in_array($alarm, ['sos', 'emergency', 'panic'], true)
            || str_contains($alarm, 'sos');
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

        $alarm = strtolower((string) ($attributes['alarm'] ?? ''));

        return in_array($alarm, ['jamming', 'gpsjamming', 'gsmjamming'], true)
            || str_contains($alarm, 'jam');
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
            if (isset($attributes['batteryLevel'])) {
                $value = (float) $attributes['batteryLevel'];
            } elseif (isset($attributes['battery'])) {
                $value = (float) $attributes['battery'];
            }
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
}
