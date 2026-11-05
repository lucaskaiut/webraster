<?php

namespace App\Modules\ExternalApi\Support;

/**
 * Catálogo de serviços externos monitorados (config/external_apis.php).
 */
class ExternalApiCatalog
{
    /**
     * Serviço correspondente à URL, ou null quando o destino não é monitorado.
     *
     * @return array{key: string, provider: string, label: string}|null
     */
    public function match(string $url): ?array
    {
        $host = $this->host($url);

        if ($host === null) {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        foreach ($this->definitions() as $key => $service) {
            if ($this->host($this->baseUrl($service)) !== $host) {
                continue;
            }

            $pattern = $service['path'] ?? null;

            if (is_string($pattern) && $pattern !== '' && preg_match($pattern, $path) !== 1) {
                continue;
            }

            return [
                'key' => $key,
                'provider' => (string) $service['provider'],
                'label' => (string) $service['label'],
            ];
        }

        return null;
    }

    /**
     * Serviços conhecidos, na ordem do catálogo.
     *
     * @return list<array{key: string, provider: string, provider_label: string, label: string}>
     */
    public function services(): array
    {
        $providers = (array) config('external_apis.providers', []);
        $services = [];

        foreach ($this->definitions() as $key => $service) {
            $provider = (string) $service['provider'];

            $services[] = [
                'key' => $key,
                'provider' => $provider,
                'provider_label' => (string) ($providers[$provider] ?? $provider),
                'label' => (string) $service['label'],
            ];
        }

        return $services;
    }

    public function label(string $key): string
    {
        $definitions = $this->definitions();

        return isset($definitions[$key]) ? (string) $definitions[$key]['label'] : $key;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return (array) config('external_apis.services', []);
    }

    /**
     * @param  array<string, mixed>  $service
     */
    private function baseUrl(array $service): string
    {
        $configKey = $service['base_url_config'] ?? null;
        $configured = is_string($configKey) ? trim((string) config($configKey)) : '';

        return $configured !== '' ? $configured : (string) ($service['base_url'] ?? '');
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower($host) : null;
    }
}
