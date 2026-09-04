<?php

namespace Tests\Unit\Billing;

use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\Enums\GatewayPaymentStatus;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Gateways\MockPixGateway;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PaymentGatewayResolverTest extends TestCase
{
    public function test_resolves_mock_pix_from_active_config(): void
    {
        config(['billing.active' => ['mockPix']]);

        $gateway = app(PaymentGatewayResolver::class)->resolve('mockPix');

        $this->assertInstanceOf(MockPixGateway::class, $gateway);
        $this->assertSame('mockPix', $gateway->key());
        $this->assertSame(PaymentMethod::PIX, $gateway->paymentMethod());
    }

    public function test_class_for_follows_naming_convention(): void
    {
        $resolver = app(PaymentGatewayResolver::class);

        $this->assertSame(
            'App\\Modules\\Billing\\Gateways\\AsaasPixGateway',
            $resolver->classFor('asaasPix'),
        );
        $this->assertSame(
            'App\\Modules\\Billing\\Gateways\\AsaasCreditCardGateway',
            $resolver->classFor('asaasCreditCard'),
        );
    }

    public function test_inactive_gateway_is_rejected(): void
    {
        config(['billing.active' => ['mockPix']]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentGatewayResolver::class)->resolve('asaasPix');
    }

    public function test_mock_pix_create_payment_returns_pix_payload(): void
    {
        $gateway = new MockPixGateway;

        $customer = $gateway->createCustomer(new CustomerDataDTO(
            name: 'Empresa Teste',
            email: 'contato@teste.com',
            document: '11222333000181',
        ));

        $payment = $gateway->createPayment(new CreatePaymentDTO(
            customerExternalId: $customer->externalId,
            amount: '49.90',
            paymentMethod: PaymentMethod::PIX,
            dueDate: CarbonImmutable::now(),
        ));

        $this->assertSame(GatewayPaymentStatus::PENDING, $payment->status);
        $this->assertSame('49.90', $payment->amount);
        $this->assertNotEmpty($payment->pixCode);
    }
}
