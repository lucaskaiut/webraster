<?php

namespace App\Modules\Shared\Support;

final class Document
{
    public static function isValidCpf(string $value): bool
    {
        $digits = (string) preg_replace('/\D+/', '', $value);

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            if ((int) $digits[$length] !== self::cpfCheckDigit($digits, $length)) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $value): bool
    {
        $digits = (string) preg_replace('/\D+/', '', $value);

        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        return (int) $digits[12] === self::cnpjCheckDigit($digits, 12)
            && (int) $digits[13] === self::cnpjCheckDigit($digits, 13);
    }

    public static function fakeCpf(): string
    {
        $digits = '';

        for ($i = 0; $i < 9; $i++) {
            $digits .= random_int(0, 9);
        }

        $digits .= self::cpfCheckDigit($digits, 9);
        $digits .= self::cpfCheckDigit($digits, 10);

        return $digits;
    }

    public static function fakeCnpj(): string
    {
        $digits = '';

        for ($i = 0; $i < 8; $i++) {
            $digits .= random_int(0, 9);
        }

        $digits .= '0001';
        $digits .= self::cnpjCheckDigit($digits, 12);
        $digits .= self::cnpjCheckDigit($digits, 13);

        return $digits;
    }

    private static function cpfCheckDigit(string $digits, int $position): int
    {
        $sum = 0;

        for ($i = 0; $i < $position; $i++) {
            $sum += (int) $digits[$i] * (($position + 1) - $i);
        }

        return ((10 * $sum) % 11) % 10;
    }

    private static function cnpjCheckDigit(string $digits, int $position): int
    {
        $weights = $position === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
