<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\DTOs\CreditCardDTO;

/**
 * Validação e sanitização de payment_data na fronteira HTTP.
 *
 * Suporta:
 * - Tokenização client-side (credit_card_token)
 * - Cartão server-side (credit_card / campos flat)
 * - Metadados genéricos (remote_ip, installments, authorize_only, recurring)
 *
 * Não contém regras específicas de gateway.
 */
final class PaymentDataRules
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $prefix = 'payment_data'): array
    {
        $key = static fn (string $field): string => "{$prefix}.{$field}";
        $card = static fn (string $field): string => "{$prefix}.credit_card.{$field}";

        return [
            $prefix => ['nullable', 'array'],

            $key('remote_ip') => ['nullable', 'string', 'max:45'],
            $key('remoteIp') => ['nullable', 'string', 'max:45'],

            $key('credit_card_token') => ['nullable', 'string', 'max:255'],
            $key('creditCardToken') => ['nullable', 'string', 'max:255'],
            $key('token') => ['nullable', 'string', 'max:255'],

            $key('installments') => ['nullable', 'integer', 'min:1', 'max:21'],
            $key('installment_count') => ['prohibited'],
            $key('installmentCount') => ['prohibited'],

            $key('authorize_only') => ['nullable', 'boolean'],
            $key('authorizeOnly') => ['nullable', 'boolean'],

            $key('recurring') => ['nullable', 'boolean'],

            $key('credit_card') => ['nullable', 'array'],
            $key('creditCard') => ['nullable', 'array'],

            $card('holder_name') => ['required_with:'.$key('credit_card'), 'string', 'max:255'],
            $card('holderName') => ['nullable', 'string', 'max:255'],
            $card('number') => ['required_with:'.$key('credit_card'), 'string', 'min:13', 'max:19'],
            $card('expiration_month') => ['required_with:'.$key('credit_card'), 'string', 'regex:/^(0?[1-9]|1[0-2])$/'],
            $card('expirationMonth') => ['nullable', 'string', 'max:2'],
            $card('expiration_year') => ['required_with:'.$key('credit_card'), 'string', 'regex:/^(\d{2}|\d{4})$/'],
            $card('expirationYear') => ['nullable', 'string', 'max:4'],
            $card('cvv') => ['required_with:'.$key('credit_card'), 'string', 'regex:/^\d{3,4}$/'],
            $card('ccv') => ['nullable', 'string', 'regex:/^\d{3,4}$/'],
            $card('holder_email') => ['nullable', 'email', 'max:255'],
            $card('holderEmail') => ['nullable', 'email', 'max:255'],
            $card('holder_document') => ['nullable', 'string', 'max:18'],
            $card('holderDocument') => ['nullable', 'string', 'max:18'],
            $card('holder_phone') => ['nullable', 'string', 'max:20'],
            $card('holderPhone') => ['nullable', 'string', 'max:20'],
            $card('postal_code') => ['nullable', 'string', 'max:9'],
            $card('postalCode') => ['nullable', 'string', 'max:9'],
            $card('address_number') => ['nullable', 'string', 'max:20'],
            $card('addressNumber') => ['nullable', 'string', 'max:20'],
            $card('address_complement') => ['nullable', 'string', 'max:100'],
            $card('addressComplement') => ['nullable', 'string', 'max:100'],

            // Aliases flat (compatibilidade com contratos que enviam card*)
            $key('card_holder_name') => ['nullable', 'string', 'max:255'],
            $key('cardHolderName') => ['nullable', 'string', 'max:255'],
            $key('card_number') => ['nullable', 'string', 'min:13', 'max:19'],
            $key('cardNumber') => ['nullable', 'string', 'min:13', 'max:19'],
            $key('card_expiration_month') => ['nullable', 'string', 'regex:/^(0?[1-9]|1[0-2])$/'],
            $key('cardExpirationMonth') => ['nullable', 'string', 'regex:/^(0?[1-9]|1[0-2])$/'],
            $key('card_expiration_year') => ['nullable', 'string', 'regex:/^(\d{2}|\d{4})$/'],
            $key('cardExpirationYear') => ['nullable', 'string', 'regex:/^(\d{2}|\d{4})$/'],
            $key('card_cvv') => ['nullable', 'string', 'regex:/^\d{3,4}$/'],
            $key('cardCvv') => ['nullable', 'string', 'regex:/^\d{3,4}$/'],
            $key('holder_name') => ['nullable', 'string', 'max:255'],
            $key('holderName') => ['nullable', 'string', 'max:255'],
            $key('number') => ['nullable', 'string', 'min:13', 'max:19'],
            $key('cvv') => ['nullable', 'string', 'regex:/^\d{3,4}$/'],
            $key('ccv') => ['nullable', 'string', 'regex:/^\d{3,4}$/'],
            $key('expiration_month') => ['nullable', 'string', 'regex:/^(0?[1-9]|1[0-2])$/'],
            $key('expiration_year') => ['nullable', 'string', 'regex:/^(\d{2}|\d{4})$/'],
            $key('exp_month') => ['nullable', 'string', 'max:2'],
            $key('exp_year') => ['nullable', 'string', 'max:4'],
            $key('postal_code') => ['nullable', 'string', 'max:9'],
            $key('postalCode') => ['nullable', 'string', 'max:9'],
            $key('address_number') => ['nullable', 'string', 'max:20'],
            $key('addressNumber') => ['nullable', 'string', 'max:20'],
            $key('address_complement') => ['nullable', 'string', 'max:100'],
            $key('addressComplement') => ['nullable', 'string', 'max:100'],
            $key('holder_email') => ['nullable', 'email', 'max:255'],
            $key('holder_document') => ['nullable', 'string', 'max:18'],
            $key('holder_phone') => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentData
     * @return array<string, mixed>
     */
    public static function sanitize(array $paymentData): array
    {
        $allowed = [
            'remote_ip',
            'remoteIp',
            'credit_card_token',
            'creditCardToken',
            'token',
            'installments',
            'authorize_only',
            'authorizeOnly',
            'recurring',
            'credit_card',
            'creditCard',
            'card_holder_name',
            'cardHolderName',
            'card_number',
            'cardNumber',
            'card_expiration_month',
            'cardExpirationMonth',
            'card_expiration_year',
            'cardExpirationYear',
            'card_cvv',
            'cardCvv',
            'holder_name',
            'holderName',
            'number',
            'cvv',
            'ccv',
            'expiration_month',
            'expiration_year',
            'exp_month',
            'exp_year',
            'postal_code',
            'postalCode',
            'address_number',
            'addressNumber',
            'address_complement',
            'addressComplement',
            'holder_email',
            'holderEmail',
            'holder_document',
            'holderDocument',
            'holder_phone',
            'holderPhone',
        ];

        $sanitized = array_intersect_key($paymentData, array_flip($allowed));

        foreach (['credit_card', 'creditCard'] as $nestedKey) {
            if (! isset($sanitized[$nestedKey]) || ! is_array($sanitized[$nestedKey])) {
                continue;
            }

            $card = CreditCardDTO::tryFromArray($sanitized[$nestedKey]);
            $sanitized['credit_card'] = $card?->toArray() ?? [];
            unset($sanitized['creditCard']);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function resolveToken(array $paymentData): ?string
    {
        foreach (['credit_card_token', 'creditCardToken', 'token'] as $key) {
            if (! array_key_exists($key, $paymentData) || $paymentData[$key] === null) {
                continue;
            }

            $value = trim((string) $paymentData[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function resolveRemoteIp(array $paymentData): ?string
    {
        foreach (['remote_ip', 'remoteIp'] as $key) {
            if (! array_key_exists($key, $paymentData) || $paymentData[$key] === null) {
                continue;
            }

            $value = trim((string) $paymentData[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function resolveInstallments(array $paymentData): ?int
    {
        if (! array_key_exists('installments', $paymentData) || $paymentData['installments'] === null) {
            return null;
        }

        return (int) $paymentData['installments'];
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function resolveAuthorizeOnly(array $paymentData): ?bool
    {
        if (array_key_exists('authorize_only', $paymentData)) {
            return (bool) $paymentData['authorize_only'];
        }

        if (array_key_exists('authorizeOnly', $paymentData)) {
            return (bool) $paymentData['authorizeOnly'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function resolveRecurring(array $paymentData): ?bool
    {
        if (! array_key_exists('recurring', $paymentData) || $paymentData['recurring'] === null) {
            return null;
        }

        return (bool) $paymentData['recurring'];
    }
}
