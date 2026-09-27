<?php

namespace App\Modules\Chat\Support;

final class PhoneNumber
{
    public static function fromRemoteJid(string $remoteJid): ?string
    {
        $local = explode('@', $remoteJid)[0] ?? '';

        if ($local === '') {
            return null;
        }

        return self::toE164Digits($local);
    }

    public static function toE164Digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
