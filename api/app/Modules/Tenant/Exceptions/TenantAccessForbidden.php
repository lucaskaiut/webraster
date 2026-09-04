<?php

namespace App\Modules\Tenant\Exceptions;

use Exception;

class TenantAccessForbidden extends Exception
{
    public static function make(): self
    {
        return new self('Acesso ao tenant informado não é permitido.');
    }
}
