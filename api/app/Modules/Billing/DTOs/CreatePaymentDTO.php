<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\PaymentMethod;
use Carbon\CarbonInterface;

/**
 * Pedido de cobrança enviado ao gateway (Payment Request).
 *
 * Compatível com PIX, cartão e futuros meios:
 * - token: tokenização client-side
 * - creditCard: dados de cartão para processamento/tokenização server-side
 * O Core não decide a estratégia — o gateway escolhe.
 */
final readonly class CreatePaymentDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $customerExternalId,
        public string $amount,
        public PaymentMethod $paymentMethod,
        public CarbonInterface $dueDate,
        public ?string $description = null,
        public ?array $metadata = null,
        public ?string $token = null,
        public ?CreditCardDTO $creditCard = null,
        public ?CustomerDataDTO $customer = null,
        public ?string $remoteIp = null,
        public ?int $installments = null,
        public ?bool $authorizeOnly = null,
        public ?bool $recurring = null,
    ) {}

    /**
     * @param  array{
     *     customer_external_id: string,
     *     amount: string|float|int,
     *     payment_method: string,
     *     due_date: CarbonInterface|string,
     *     description?: ?string,
     *     metadata?: ?array,
     *     token?: ?string,
     *     credit_card?: ?array|CreditCardDTO,
     *     customer?: ?array|CustomerDataDTO,
     *     remote_ip?: ?string,
     *     installments?: ?int,
     *     authorize_only?: ?bool,
     *     recurring?: ?bool
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $dueDate = $data['due_date'] instanceof CarbonInterface
            ? $data['due_date']
            : \Carbon\CarbonImmutable::parse($data['due_date']);

        $creditCard = $data['credit_card'] ?? $data['creditCard'] ?? null;

        if (is_array($creditCard)) {
            $creditCard = CreditCardDTO::tryFromArray($creditCard);
        } elseif (! $creditCard instanceof CreditCardDTO) {
            $creditCard = null;
        }

        $customer = $data['customer'] ?? null;

        if (is_array($customer)) {
            $customer = CustomerDataDTO::fromArray($customer);
        } elseif (! $customer instanceof CustomerDataDTO) {
            $customer = null;
        }

        return new self(
            customerExternalId: $data['customer_external_id'],
            amount: number_format((float) $data['amount'], 2, '.', ''),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            dueDate: $dueDate,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
            token: isset($data['token']) ? (string) $data['token'] : null,
            creditCard: $creditCard,
            customer: $customer,
            remoteIp: isset($data['remote_ip']) ? (string) $data['remote_ip'] : ($data['remoteIp'] ?? null),
            installments: isset($data['installments']) ? (int) $data['installments'] : null,
            authorizeOnly: array_key_exists('authorize_only', $data)
                ? (bool) $data['authorize_only']
                : (array_key_exists('authorizeOnly', $data) ? (bool) $data['authorizeOnly'] : null),
            recurring: array_key_exists('recurring', $data) ? (bool) $data['recurring'] : null,
        );
    }

    public function hasToken(): bool
    {
        return filled($this->token);
    }

    public function hasCreditCard(): bool
    {
        return $this->creditCard !== null;
    }
}
