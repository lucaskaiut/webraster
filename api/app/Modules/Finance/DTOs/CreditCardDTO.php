<?php

namespace App\Modules\Finance\DTOs;

final readonly class CreditCardDTO
{
    /**
     * @param  array<string, mixed>|null  $holderInfo
     */
    public function __construct(
        public string $holderName,
        public string $number,
        public string $expirationMonth,
        public string $expirationYear,
        public string $cvv,
        public ?array $holderInfo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function tryFromArray(array $data): ?self
    {
        $nested = is_array($data['credit_card'] ?? null) ? $data['credit_card'] : $data;
        $holderName = trim((string) ($nested['holderName'] ?? $nested['holder_name'] ?? ''));
        $number = preg_replace('/\D+/', '', (string) ($nested['number'] ?? '')) ?? '';
        $month = (string) ($nested['expiryMonth'] ?? $nested['expiration_month'] ?? $nested['expiry_month'] ?? '');
        $year = (string) ($nested['expiryYear'] ?? $nested['expiration_year'] ?? $nested['expiry_year'] ?? '');
        $cvv = (string) ($nested['ccv'] ?? $nested['cvv'] ?? '');

        if ($holderName === '' || $number === '' || $month === '' || $year === '' || $cvv === '') {
            return null;
        }

        $holderInfo = is_array($data['creditCardHolderInfo'] ?? null)
            ? $data['creditCardHolderInfo']
            : (is_array($data['holder_info'] ?? null) ? $data['holder_info'] : null);

        return new self(
            holderName: $holderName,
            number: $number,
            expirationMonth: str_pad((string) (int) preg_replace('/\D+/', '', $month), 2, '0', STR_PAD_LEFT),
            expirationYear: strlen(preg_replace('/\D+/', '', $year) ?? '') === 2
                ? '20'.preg_replace('/\D+/', '', $year)
                : (string) preg_replace('/\D+/', '', $year),
            cvv: $cvv,
            holderInfo: $holderInfo,
        );
    }
}
