<?php

namespace App\Modules\Billing\DTOs;

/**
 * Dados de cartão de crédito enviados pelo cliente (tokenização/processamento server-side).
 * Gateway-agnóstico: cada provider decide como consumir estes campos.
 */
final readonly class CreditCardDTO
{
    public function __construct(
        public string $holderName,
        public string $number,
        public string $expirationMonth,
        public string $expirationYear,
        public string $cvv,
        public ?string $holderEmail = null,
        public ?string $holderDocument = null,
        public ?string $holderPhone = null,
        public ?string $postalCode = null,
        public ?string $addressNumber = null,
        public ?string $addressComplement = null,
    ) {}

    /**
     * Extrai cartão de payment_data (objeto aninhado ou campos flat).
     *
     * @param  array<string, mixed>  $paymentData
     */
    public static function tryFromPaymentData(array $paymentData): ?self
    {
        $nested = $paymentData['credit_card']
            ?? $paymentData['creditCard']
            ?? null;

        if (is_array($nested)) {
            return self::tryFromArray($nested);
        }

        return self::tryFromArray($paymentData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function tryFromArray(array $data): ?self
    {
        $holderName = self::stringFrom($data, [
            'holder_name', 'holderName', 'card_holder_name', 'cardHolderName',
        ]);
        $number = self::digitsFrom($data, [
            'number', 'card_number', 'cardNumber',
        ]);
        $expirationMonth = self::stringFrom($data, [
            'expiration_month', 'expirationMonth', 'expiry_month', 'expiryMonth',
            'exp_month', 'expMonth', 'card_expiration_month', 'cardExpirationMonth',
        ]);
        $expirationYear = self::stringFrom($data, [
            'expiration_year', 'expirationYear', 'expiry_year', 'expiryYear',
            'exp_year', 'expYear', 'card_expiration_year', 'cardExpirationYear',
        ]);
        $cvv = self::stringFrom($data, [
            'cvv', 'ccv', 'card_cvv', 'cardCvv',
        ]);

        if (
            $holderName === null
            || $number === null
            || $expirationMonth === null
            || $expirationYear === null
            || $cvv === null
        ) {
            return null;
        }

        return new self(
            holderName: $holderName,
            number: $number,
            expirationMonth: self::normalizeMonth($expirationMonth),
            expirationYear: self::normalizeYear($expirationYear),
            cvv: $cvv,
            holderEmail: self::stringFrom($data, ['holder_email', 'holderEmail', 'email']),
            holderDocument: self::digitsFrom($data, [
                'holder_document', 'holderDocument', 'cpf_cnpj', 'cpfCnpj', 'document',
            ]),
            holderPhone: self::digitsFrom($data, [
                'holder_phone', 'holderPhone', 'phone', 'mobile_phone', 'mobilePhone',
            ]),
            postalCode: self::digitsFrom($data, [
                'postal_code', 'postalCode', 'zip_code', 'zipCode',
            ]),
            addressNumber: self::stringFrom($data, [
                'address_number', 'addressNumber',
            ]),
            addressComplement: self::stringFrom($data, [
                'address_complement', 'addressComplement',
            ]),
        );
    }

    /**
     * @return array{
     *     holder_name: string,
     *     number: string,
     *     expiration_month: string,
     *     expiration_year: string,
     *     cvv: string,
     *     holder_email: ?string,
     *     holder_document: ?string,
     *     holder_phone: ?string,
     *     postal_code: ?string,
     *     address_number: ?string,
     *     address_complement: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'holder_name' => $this->holderName,
            'number' => $this->number,
            'expiration_month' => $this->expirationMonth,
            'expiration_year' => $this->expirationYear,
            'cvv' => $this->cvv,
            'holder_email' => $this->holderEmail,
            'holder_document' => $this->holderDocument,
            'holder_phone' => $this->holderPhone,
            'postal_code' => $this->postalCode,
            'address_number' => $this->addressNumber,
            'address_complement' => $this->addressComplement,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     */
    private static function stringFrom(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $source) || $source[$key] === null) {
                continue;
            }

            $value = trim((string) $source[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     */
    private static function digitsFrom(array $source, array $keys): ?string
    {
        $value = self::stringFrom($source, $keys);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private static function normalizeMonth(string $month): string
    {
        $digits = preg_replace('/\D+/', '', $month) ?? '';

        if ($digits === '') {
            return $month;
        }

        return str_pad((string) (int) $digits, 2, '0', STR_PAD_LEFT);
    }

    private static function normalizeYear(string $year): string
    {
        $digits = preg_replace('/\D+/', '', $year) ?? '';

        if (strlen($digits) === 2) {
            return '20'.$digits;
        }

        return $digits !== '' ? $digits : $year;
    }
}
