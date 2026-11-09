<?php

namespace App\Modules\Tenant\Exceptions;

use Exception;

/**
 * Sessão de um usuário usada no domínio de outra empresa.
 */
class TenantDomainMismatch extends Exception
{
    /** Chave em "errors" para os clientes distinguirem do 403 de permissão. */
    public const ERROR_KEY = 'tenant_domain_mismatch';

    public static function make(): self
    {
        return new self('Seu usuário não pertence a esta empresa. Acesse pelo endereço da sua empresa.');
    }
}
