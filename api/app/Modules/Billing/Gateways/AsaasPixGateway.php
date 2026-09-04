<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayCustomerDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\PaymentMethod;
use RuntimeException;

/**
 * Placeholder para integração real Asaas + PIX (chave: asaasPix).
 * Ative em config/billing.php → active quando a integração estiver pronta.
 */
class AsaasPixGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'asaasPix';
    }

    public function label(): string
    {
        return 'PIX (Asaas)';
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::PIX;
    }

    public function createCustomer(CustomerDataDTO $customer): GatewayCustomerDTO
    {
        throw $this->notImplemented();
    }

    public function updateCustomer(string $externalCustomerId, CustomerDataDTO $customer): void
    {
        throw $this->notImplemented();
    }

    public function createPayment(CreatePaymentDTO $payment): GatewayPaymentDTO
    {
        throw $this->notImplemented();
    }

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO
    {
        throw $this->notImplemented();
    }

    public function cancelPayment(string $externalPaymentId): void
    {
        throw $this->notImplemented();
    }

    private function notImplemented(): RuntimeException
    {
        return new RuntimeException('AsaasPixGateway ainda não foi implementado. Remova-o de billing.active até concluir a integração.');
    }
}
