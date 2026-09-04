<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PaymentGatewayResolver
{
    private const NAMESPACE = 'App\\Modules\\Billing\\Gateways\\';

    /**
     * @return list<string>
     */
    public function activeKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values(array_filter(
            config('billing.active', []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return $keys;
    }

    public function isActive(string $key): bool
    {
        return in_array($key, $this->activeKeys(), true);
    }

    /**
     * Primeiro gateway ativo — usado como padrão quando o cadastro não exige pagamento agora (trial).
     */
    public function defaultKey(): ?string
    {
        foreach ($this->activeKeys() as $key) {
            try {
                $this->assertActive($key);

                return $key;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    public function assertActive(string $key): void
    {
        if (! $this->isActive($key)) {
            throw ValidationException::withMessages([
                'payment_gateway' => ['Forma de pagamento inválida ou indisponível.'],
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
                "Gateway [{$class}] retornou key [{$gateway->key()}] diferente de [{$key}]."
            );
        }

        return $gateway;
    }

    /**
     * Converte mockPix → App\Modules\Billing\Gateways\MockPixGateway
     */
    public function classFor(string $key): string
    {
        return self::NAMESPACE.Str::studly($key).'Gateway';
    }

    /**
     * Catálogo das formas de pagamento ativas (para UI / API).
     *
     * @return list<array{key: string, label: string, payment_method: string}>
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
                'payment_method' => $gateway->paymentMethod()->value,
            ];
        }

        return $items;
    }
}
