<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGatewayInterface;
use App\Modules\Billing\DTOs\CreatePaymentDTO;
use App\Modules\Billing\DTOs\CreditCardDTO;
use App\Modules\Billing\DTOs\CustomerDataDTO;
use App\Modules\Billing\DTOs\GatewayCustomerDTO;
use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Gateways\Asaas\AsaasClient;
use App\Modules\Billing\Gateways\Asaas\AsaasException;
use App\Modules\Billing\Gateways\Asaas\AsaasPaymentMapper;
use Illuminate\Validation\ValidationException;

/**
 * Gateway Asaas + cartão de crédito (chave: asaasCreditCard).
 *
 * Estratégias (decididas neste provider, não no Core):
 * - token → creditCardToken (tokenização client-side / reutilização)
 * - creditCard → creditCard + creditCardHolderInfo (server-side)
 * - nenhum → cobrança + invoiceUrl (checkout hospedado Asaas)
 *
 * CreatePaymentDTO::$recurring chega neste gateway para uso futuro
 * (tokenização/recorrência); ainda não altera o payload Asaas.
 */
class AsaasCreditCardGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly AsaasClient $asaas,
    ) {}

    public function key(): string
    {
        return 'asaasCreditCard';
    }

    public function label(): string
    {
        return 'Cartão de crédito (Asaas)';
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function createCustomer(CustomerDataDTO $customer): GatewayCustomerDTO
    {
        $document = $this->digits($customer->document);

        if ($document !== '') {
            $existing = $this->asaas->listCustomers(['cpfCnpj' => $document]);
            $first = $existing['data'][0] ?? null;

            if (is_array($first) && filled($first['id'] ?? null)) {
                return $this->mapCustomer($first);
            }
        }

        $payload = array_filter([
            'name' => $customer->name,
            'email' => $customer->email,
            'cpfCnpj' => $document !== '' ? $document : null,
            'mobilePhone' => $this->digits($customer->phone),
            'externalReference' => $customer->externalId,
            'notificationDisabled' => true,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            return $this->mapCustomer($this->asaas->createCustomer($payload));
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'customer');
        }
    }

    public function updateCustomer(string $externalCustomerId, CustomerDataDTO $customer): void
    {
        $payload = array_filter([
            'name' => $customer->name,
            'email' => $customer->email,
            'cpfCnpj' => $this->digits($customer->document) ?: null,
            'mobilePhone' => $this->digits($customer->phone),
            'externalReference' => $customer->externalId,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            $this->asaas->updateCustomer($externalCustomerId, $payload);
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'customer');
        }
    }

    public function createPayment(CreatePaymentDTO $payment): GatewayPaymentDTO
    {
        if ($payment->paymentMethod !== PaymentMethod::CREDIT_CARD) {
            throw ValidationException::withMessages([
                'payment_method' => ['AsaasCreditCardGateway aceita apenas pagamento via cartão de crédito.'],
            ]);
        }

        $remoteIp = trim((string) ($payment->remoteIp ?? ''));

        if ($remoteIp === '') {
            throw ValidationException::withMessages([
                'payment_data.remote_ip' => ['O IP do pagador (remote_ip) é obrigatório para cobranças com cartão.'],
            ]);
        }

        $payload = [
            'customer' => $payment->customerExternalId,
            'billingType' => 'CREDIT_CARD',
            'value' => (float) $payment->amount,
            'dueDate' => $payment->dueDate->format('Y-m-d'),
            'description' => $this->truncate($payment->description ?? 'Assinatura', 500),
            'externalReference' => $payment->metadata['invoice_uuid']
                ?? $payment->metadata['subscription_uuid']
                ?? null,
            'remoteIp' => $remoteIp,
        ];

        $installments = $this->resolveInstallments($payment->installments);

        if ($installments > 1) {
            $payload['installmentCount'] = $installments;
            $payload['totalValue'] = (float) $payment->amount;
        }

        if ($payment->authorizeOnly !== null) {
            $payload['authorizeOnly'] = $payment->authorizeOnly;
        }

        if ($payment->hasToken()) {
            $payload['creditCardToken'] = $payment->token;
        } elseif ($payment->hasCreditCard()) {
            $payload = [
                ...$payload,
                ...$this->buildCreditCardPayload($payment->creditCard, $payment->customer),
            ];
        }

        $payload = array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        try {
            $response = $this->asaas->createPayment(
                $payload,
                (int) config('asaas.credit_card_timeout', 60),
            );
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'payment');
        }

        $gatewayPayment = AsaasPaymentMapper::toGatewayPayment($response);

        if ($payment->recurring === true) {
            $this->assertRecurringTokenPresent($gatewayPayment);
        }

        return $gatewayPayment;
    }

    private function assertRecurringTokenPresent(GatewayPaymentDTO $gatewayPayment): void
    {
        $token = $gatewayPayment->metadata['credit_card_token'] ?? null;

        if (! is_string($token) || trim($token) === '') {
            throw ValidationException::withMessages([
                'payment_data.recurring' => [
                    'Não foi possível obter o token do cartão para cobrança recorrente. Tente novamente ou use outro cartão.',
                ],
            ]);
        }
    }

    public function getPayment(string $externalPaymentId): GatewayPaymentDTO
    {
        try {
            return AsaasPaymentMapper::toGatewayPayment(
                $this->asaas->getPayment($externalPaymentId),
            );
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'payment');
        }
    }

    public function cancelPayment(string $externalPaymentId): void
    {
        try {
            $this->asaas->deletePayment($externalPaymentId);
        } catch (AsaasException $e) {
            throw $this->toValidationException($e, 'payment');
        }
    }

    /**
     * @return array{creditCard: array<string, string>, creditCardHolderInfo: array<string, string>}
     */
    private function buildCreditCardPayload(CreditCardDTO $card, ?CustomerDataDTO $customer): array
    {
        $holderName = $card->holderName;
        $holderEmail = $card->holderEmail ?: $customer?->email;
        $holderDocument = $this->digits($card->holderDocument ?: $customer?->document);
        $holderPhone = $this->digits($card->holderPhone ?: $customer?->phone);
        $postalCode = $this->digits($card->postalCode);
        $addressNumber = trim((string) ($card->addressNumber ?? ''));

        $missing = [];

        if ($holderEmail === null || $holderEmail === '') {
            $missing['payment_data.credit_card.holder_email'] = ['Informe o e-mail do portador do cartão.'];
        }

        if ($holderDocument === '') {
            $missing['payment_data.credit_card.holder_document'] = ['Informe o CPF/CNPJ do portador do cartão.'];
        }

        if ($postalCode === '') {
            $missing['payment_data.credit_card.postal_code'] = ['Informe o CEP do portador do cartão.'];
        }

        if ($addressNumber === '') {
            $missing['payment_data.credit_card.address_number'] = ['Informe o número do endereço do portador.'];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $holderInfo = array_filter([
            'name' => $holderName,
            'email' => $holderEmail,
            'cpfCnpj' => $holderDocument,
            'postalCode' => $postalCode,
            'addressNumber' => $addressNumber,
            'addressComplement' => $card->addressComplement,
            'phone' => $holderPhone !== '' ? $holderPhone : null,
            'mobilePhone' => $holderPhone !== '' ? $holderPhone : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'creditCard' => [
                'holderName' => $card->holderName,
                'number' => $card->number,
                'expiryMonth' => $card->expirationMonth,
                'expiryYear' => $card->expirationYear,
                'ccv' => $card->cvv,
            ],
            'creditCardHolderInfo' => $holderInfo,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapCustomer(array $payload): GatewayCustomerDTO
    {
        return new GatewayCustomerDTO(
            externalId: (string) ($payload['id'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            metadata: array_filter([
                'document' => $payload['cpfCnpj'] ?? null,
                'phone' => $payload['mobilePhone'] ?? $payload['phone'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    private function resolveInstallments(?int $installments): int
    {
        $count = $installments ?? 1;

        if ($count < 1) {
            return 1;
        }

        if ($count > 21) {
            throw ValidationException::withMessages([
                'payment_data.installments' => ['O número máximo de parcelas é 21 (Visa/Master) ou 12 (demais bandeiras).'],
            ]);
        }

        return $count;
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max);
    }

    private function toValidationException(AsaasException $e, string $field): ValidationException
    {
        if ($e->isClientError()) {
            return ValidationException::withMessages([
                $field => [$e->getMessage()],
            ]);
        }

        throw $e;
    }
}
