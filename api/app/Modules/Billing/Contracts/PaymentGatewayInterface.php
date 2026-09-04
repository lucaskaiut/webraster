<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayCustomerDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;

/**
 * Porta de saída para gateways de pagamento.
 * O domínio de assinaturas depende apenas desta interface.
 *
 * createPayment recebe CreatePaymentDTO (Payment Request) com campos
 * opcionais tipados (token, creditCard, customer, remoteIp, installments)
 * sem acoplamento a provedores específicos.
 *
 * Convenção de nomenclatura:
 *   chave config (camelCase) → Classe Studly + Gateway
 *   asaasPix → AsaasPixGateway
 *   asaasCreditCard → AsaasCreditCardGateway
 */
interface PaymentGatewayInterface
{
    /**
     * Chave estável usada em config/billing.php e na assinatura (ex.: asaasPix).
     */
    public function key(): string;

    /**
     * Rótulo amigável para exibição ao usuário.
     */
    public function label(): string;

    public function paymentMethod(): \App\Modules\Billing\Enums\PaymentMethod;

    public function createCustomer(CustomerDataDTO $customer): GatewayCustomerDTO;

    public function updateCustomer(string $externalCustomerId, CustomerDataDTO $customer): void;

    public function createPayment(CreatePaymentDTO $payment): GatewayPaymentDTO;

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO;

    public function cancelPayment(string $externalPaymentId): void;
}
