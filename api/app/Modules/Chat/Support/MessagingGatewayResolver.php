<?php

namespace App\Modules\Chat\Support;

use App\Modules\Chat\Contracts\MessagingGatewayInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MessagingGatewayResolver
{
    private const NAMESPACE = 'App\\Modules\\Chat\\Gateways\\';

    /**
     * @return list<string>
     */
    public function activeKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values(array_filter(
            config('chat.active_providers', []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return $keys;
    }

    public function assertActive(string $key): void
    {
        if (! in_array($key, $this->activeKeys(), true)) {
            throw ValidationException::withMessages([
                'provider' => ['Provedor de mensagens indisponível.'],
            ]);
        }

        if (! class_exists($this->classFor($key))) {
            throw ValidationException::withMessages([
                'provider' => ["Provedor [{$key}] sem implementação."],
            ]);
        }
    }

    public function resolve(string $key): MessagingGatewayInterface
    {
        $this->assertActive($key);

        $gateway = app($this->classFor($key));

        if (! $gateway instanceof MessagingGatewayInterface) {
            throw new RuntimeException('Gateway inválido.');
        }

        if ($gateway->key() !== $key) {
            throw new RuntimeException('Gateway key mismatch.');
        }

        return $gateway;
    }

    public function classFor(string $key): string
    {
        return self::NAMESPACE.Str::studly($key).'Gateway';
    }

    /**
     * @return list<array{key: string, label: string, capabilities: list<string>}>
     */
    public function catalog(): array
    {
        $items = [];

        foreach ($this->activeKeys() as $key) {
            try {
                $gateway = $this->resolve($key);
            } catch (\Throwable) {
                continue;
            }

            $items[] = [
                'key' => $gateway->key(),
                'label' => $gateway->label(),
                'capabilities' => $gateway->capabilities(),
            ];
        }

        return $items;
    }
}
