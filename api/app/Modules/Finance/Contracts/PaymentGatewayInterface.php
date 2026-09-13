<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\DTOs\CreateGatewayPaymentDTO;
use App\Modules\Finance\DTOs\GatewayCustomerDTO;
use App\Modules\Finance\DTOs\GatewayPaymentDTO;
use App\Modules\Finance\DTOs\GatewayWebhookEventDTO;
use App\Modules\Finance\Enums\PaymentMethod;
use Illuminate\Http\Request;

/**
 * Porta de saída para gateways de pagamento do Finance.
 * Charge, webhook e liquidação dependem apenas deste contrato.
 *
 * Convenção de nomenclatura (igual ao Billing):
 *   chave config (camelCase) → Classe Studly + Gateway
 *   asaas → AsaasGateway
 */
interface PaymentGatewayInterface
{
    public function key(): string;

    public function label(): string;

    public function supports(PaymentMethod $method): bool;

    public function isReady(): bool;

    /**
     * Campos de credencial para a UI (o core não conhece chaves do provedor).
     *
     * @return list<array{
     *     name: string,
     *     label: string,
     *     type: string,
     *     required?: bool,
     *     secret?: bool,
     *     hint?: string,
     *     options?: list<array{value: string, label: string}>
     * }>
     */
    public function credentialSchema(): array;

    /**
     * Valores públicos (sem segredos) das credenciais atuais.
     *
     * @return array<string, mixed>
     */
    public function publicCredentials(): array;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function saveConfig(array $credentials, bool $isActive): void;

    public function webhookUrl(): string;

    public function ensureCustomer(Client $client): GatewayCustomerDTO;

    public function createPayment(CreateGatewayPaymentDTO $payment): GatewayPaymentDTO;

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO;

    public function authenticateWebhook(Request $request): bool;

    public function parseWebhook(Request $request): GatewayWebhookEventDTO;
}
