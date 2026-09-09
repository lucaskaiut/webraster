<?php

namespace App\Modules\Finance\Enums;

enum AsaasEnvironment: string
{
    case SANDBOX = 'sandbox';
    case PRODUCTION = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::SANDBOX => 'https://api-sandbox.asaas.com/v3',
            self::PRODUCTION => 'https://api.asaas.com/v3',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SANDBOX => 'Sandbox',
            self::PRODUCTION => 'Produção',
        };
    }
}
