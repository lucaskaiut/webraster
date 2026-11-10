<?php

namespace App\Modules\Finance\Exceptions;

use Exception;

class ClientFinanciallyBlocked extends Exception
{
    /** Chave em "errors" para os clientes distinguirem do 403 de permissão. */
    public const ERROR_KEY = 'client_financial_block';

    public static function make(?string $message = null): self
    {
        return new self($message ?: 'Seu acesso está restrito ao financeiro por causa de cobranças em atraso.');
    }
}
