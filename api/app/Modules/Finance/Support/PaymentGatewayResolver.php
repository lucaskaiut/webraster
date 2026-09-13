<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use App\Modules\Finance\Enums\PaymentMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PaymentGatewayResolver
{
    private const NAMESPACE = 'App\\Modules\\Finance\\Gateways\\';

    /**
     * @return list<string>
     */
    public function activeKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values(array_filter(
            config('finance.active', []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return $keys;
    }

    public function isActive(string $key): bool
    {
        return in_array($key, $this->activeKeys(), true);
    }

    public function defaultKey(): ?string
    {
        return $this->activeKeys()[0] ?? null;
    }

    /**
     * @throws ValidationException
     */
    public function assertActive(string $key): void
    {
        if (! $this->isActive($key)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Gateway de pagamento inválido ou indisponível.'],
            ]);
        }

        $class = $this->classFor($key);

        if (! class_exists($class)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ["Gateway [{$key}] não possui implementação ({$class})."],
            ]);
        }
    }

    public function resolve(string $key): PaymentGatewayInterface
    {
        $this->assertActive($key);

        $class = $this->classFor($key);
        $gateway = app($class);

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw new RuntimeException("A classe {$class} deve implementar PaymentGatewayInterface.");
        }

        if ($gateway->key() !== $key) {
            throw new RuntimeException(
                "Gateway [{$class}] retornou key [{$gateway->key()}] diferente de [{$key}].",
            );
        }

        return $gateway;
    }

    public function resolveReadyFor(PaymentMethod $method): PaymentGatewayInterface
    {
        foreach ($this->activeKeys() as $key) {
            try {
                $gateway = $this->resolve($key);
            } catch (\Throwable) {
                continue;
            }

            if ($gateway->supports($method) && $gateway->isReady()) {
                return $gateway;
            }
        }

        throw ValidationException::withMessages([
            'payment_gateway' => [
                'Pagamento indisponível: configure um gateway ativo em Financeiro → Gateway.',
            ],
        ]);
    }

    public function classFor(string $key): string
    {
        return self::NAMESPACE.Str::studly($key).'Gateway';
    }

    /**
     * @return list<array{key: string, label: string, ready: bool}>
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
                'ready' => $gateway->isReady(),
            ];
        }

        return $items;
    }
}
