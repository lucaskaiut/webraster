<?php

namespace App\Modules\Tenant\Exceptions;

use Exception;

class TenantInactive extends Exception
{
    /** Chave em "errors" para os clientes distinguirem do 403 de permissão. */
    public const ERROR_KEY = 'tenant_inactive';

    public static function make(): self
    {
        return new self('O acesso desta empresa está suspenso. Entre em contato com o administrador.');
    }
}
