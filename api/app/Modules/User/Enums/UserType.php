<?php

namespace App\Modules\User\Enums;

/**
 * Tipo da conta do usuário, independente dos papéis de ACL.
 *
 * - STAFF: equipe interna (administrador, operador etc.)
 * - TECHNICIAN: técnico de campo, com experiência própria no aplicativo
 * - CLIENT: usuário do portal do cliente final
 */
enum UserType: string
{
    case STAFF = 'staff';
    case TECHNICIAN = 'technician';
    case CLIENT = 'client';

    public function label(): string
    {
        return match ($this) {
            self::STAFF => 'Equipe',
            self::TECHNICIAN => 'Técnico',
            self::CLIENT => 'Cliente',
        };
    }
}
