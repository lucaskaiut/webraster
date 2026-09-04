<?php

namespace App\Modules\Tenant\Exceptions;

use Exception;

class TenantCouldNotBeResolved extends Exception
{
    public static function make(): self
    {
        return new self('Tenant não encontrado para a requisição atual.');
    }
}
