<?php

namespace App\Modules\VehicleData\Support;

use App\Modules\VehicleData\Contracts\PlateLookupProvider;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolve a implementação ativa de consulta de placa a partir da config.
 *
 * Convenção de nomenclatura (igual ao Finance):
 *   chave config (camelCase) → Classe Studly + Provider
 *   apiPlacas → ApiPlacasProvider
 */
final class PlateLookupResolver
{
    private const NAMESPACE = 'App\\Modules\\VehicleData\\Gateways\\';

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values(array_filter(
            (array) config('vehicledata.providers', []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return $keys;
    }

    public function defaultKey(): string
    {
        $default = (string) config('vehicledata.default', '');

        return $default !== '' ? $default : ($this->keys()[0] ?? 'apiPlacas');
    }

    public function resolve(?string $key = null): PlateLookupProvider
    {
        $key = $key ?: $this->defaultKey();
        $class = self::NAMESPACE.Str::studly($key).'Provider';

        if (! class_exists($class)) {
            throw new RuntimeException("Provedor de consulta de placa [{$key}] não possui implementação ({$class}).");
        }

        $provider = app($class);

        if (! $provider instanceof PlateLookupProvider) {
            throw new RuntimeException("A classe {$class} deve implementar PlateLookupProvider.");
        }

        if ($provider->key() !== $key) {
            throw new RuntimeException(
                "Provedor [{$class}] retornou key [{$provider->key()}] diferente de [{$key}].",
            );
        }

        return $provider;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function catalog(): array
    {
        $items = [];

        foreach ($this->keys() as $key) {
            try {
                $provider = $this->resolve($key);
            } catch (\Throwable) {
                continue;
            }

            $items[] = [
                'key' => $provider->key(),
                'label' => $provider->label(),
            ];
        }

        return $items;
    }
}
