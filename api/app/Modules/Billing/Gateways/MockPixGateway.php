<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayCustomerDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\GatewayPaymentStatus;
use App\Modules\Billing\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pseudo-gateway PIX (chave: mockPix).
 */
class MockPixGateway implements PaymentGatewayInterface
{
    /**
     * @var array<string, array{customer: GatewayCustomerDTO, payments: array<string, GatewayPaymentDTO>}>
     */
    private array $store = [];

    /** Simula captura imediata (cartão Asaas CONFIRMED no createPayment). */
    private bool $instantPaid = false;

    public function payInstantly(bool $value = true): self
    {
        $this->instantPaid = $value;

        return $this;
    }

    public function key(): string
    {
        return 'mockPix';
    }

    public function label(): string
    {
        return 'PIX (simulado)';
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::PIX;
    }

    public function createCustomer(CustomerDataDTO $customer): GatewayCustomerDTO
    {
        $externalId = 'cus_'.Str::lower(Str::random(16));

        $gatewayCustomer = new GatewayCustomerDTO(
            externalId: $externalId,
            name: $customer->name,
            email: $customer->email,
            metadata: [
                'document' => $customer->document,
                'phone' => $customer->phone,
            ],
        );

        $this->store[$externalId] = [
            'customer' => $gatewayCustomer,
            'payments' => [],
        ];

        return $gatewayCustomer;
    }

    public function updateCustomer(string $externalCustomerId, CustomerDataDTO $customer): void
    {
        $this->assertCustomerExists($externalCustomerId);

        $this->store[$externalCustomerId]['customer'] = new GatewayCustomerDTO(
            externalId: $externalCustomerId,
            name: $customer->name,
            email: $customer->email,
            metadata: [
                'document' => $customer->document,
                'phone' => $customer->phone,
            ],
        );
    }

    public function createPayment(CreatePaymentDTO $payment): GatewayPaymentDTO
    {
        if ($payment->paymentMethod !== PaymentMethod::PIX) {
            throw new RuntimeException('MockPixGateway aceita apenas pagamento via PIX.');
        }

        $this->assertCustomerExists($payment->customerExternalId);

        $externalId = 'pay_'.Str::lower(Str::random(20));
        $expiresAt = CarbonImmutable::instance($payment->dueDate)->addDay();
        $pixCode = sprintf(
            '00020126580014BR.GOV.BCB.PIX0136%s520400005303986540%s5802BR5925NOX CONNECT LTDA6009SAO PAULO62070503***6304ABCD',
            Str::uuid()->toString(),
            $payment->amount,
        );

        $gatewayPayment = new GatewayPaymentDTO(
            externalId: $externalId,
            status: $this->instantPaid ? GatewayPaymentStatus::PAID : GatewayPaymentStatus::PENDING,
            amount: $payment->amount,
            pixCode: $pixCode,
            pixQrcode: 'data:image/svg+xml;base64,'.base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="120" height="120" fill="#fff"/><text x="10" y="60" font-size="10">PIX QR</text></svg>'
            ),
            expiresAt: $expiresAt,
            metadata: $payment->metadata,
        );

        $this->store[$payment->customerExternalId]['payments'][$externalId] = $gatewayPayment;
        $this->rememberPayment($externalId, $gatewayPayment);

        return $gatewayPayment;
    }

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO
    {
        $payment = $this->findPayment($externalPaymentId);

        if ($payment === null) {
            throw new RuntimeException("Pagamento {$externalPaymentId} não encontrado no gateway.");
        }

        return $payment;
    }

    public function cancelPayment(string $externalPaymentId): void
    {
        $payment = $this->getPayment($externalPaymentId);

        $cancelled = new GatewayPaymentDTO(
            externalId: $payment->externalId,
            status: GatewayPaymentStatus::CANCELLED,
            amount: $payment->amount,
            pixCode: $payment->pixCode,
            pixQrcode: $payment->pixQrcode,
            expiresAt: $payment->expiresAt,
            metadata: $payment->metadata,
        );

        $this->rememberPayment($externalPaymentId, $cancelled);
    }

    public function markAsPaid(string $externalPaymentId): GatewayPaymentDTO
    {
        $payment = $this->getPayment($externalPaymentId);

        $paid = new GatewayPaymentDTO(
            externalId: $payment->externalId,
            status: GatewayPaymentStatus::PAID,
            amount: $payment->amount,
            pixCode: $payment->pixCode,
            pixQrcode: $payment->pixQrcode,
            expiresAt: $payment->expiresAt,
            metadata: $payment->metadata,
        );

        $this->rememberPayment($externalPaymentId, $paid);

        return $paid;
    }

    public function markAsExpired(string $externalPaymentId): GatewayPaymentDTO
    {
        $payment = $this->getPayment($externalPaymentId);

        $expired = new GatewayPaymentDTO(
            externalId: $payment->externalId,
            status: GatewayPaymentStatus::EXPIRED,
            amount: $payment->amount,
            pixCode: $payment->pixCode,
            pixQrcode: $payment->pixQrcode,
            expiresAt: $payment->expiresAt,
            metadata: $payment->metadata,
        );

        $this->rememberPayment($externalPaymentId, $expired);

        return $expired;
    }

    private function assertCustomerExists(string $externalCustomerId): void
    {
        if (! isset($this->store[$externalCustomerId])) {
            $this->store[$externalCustomerId] = [
                'customer' => new GatewayCustomerDTO(
                    externalId: $externalCustomerId,
                    name: 'Customer',
                    email: 'customer@example.com',
                ),
                'payments' => [],
            ];
        }
    }

    private function findPayment(string $externalPaymentId): ?GatewayPaymentDTO
    {
        $cache = app()->bound('billing.pix_payments')
            ? app('billing.pix_payments')
            : [];

        if (isset($cache[$externalPaymentId]) && $cache[$externalPaymentId] instanceof GatewayPaymentDTO) {
            return $cache[$externalPaymentId];
        }

        foreach ($this->store as $bucket) {
            if (isset($bucket['payments'][$externalPaymentId])) {
                return $bucket['payments'][$externalPaymentId];
            }
        }

        return null;
    }

    private function rememberPayment(string $externalPaymentId, GatewayPaymentDTO $payment): void
    {
        if (! app()->bound('billing.pix_payments')) {
            app()->instance('billing.pix_payments', []);
        }

        /** @var array<string, GatewayPaymentDTO> $cache */
        $cache = app('billing.pix_payments');
        $cache[$externalPaymentId] = $payment;
        app()->instance('billing.pix_payments', $cache);

        foreach ($this->store as $customerId => $bucket) {
            if (isset($bucket['payments'][$externalPaymentId])) {
                $this->store[$customerId]['payments'][$externalPaymentId] = $payment;

                return;
            }
        }
    }
}
