<?php

namespace App\Modules\ACL\Enums;

enum DefaultRole: string
{
    case ADMINISTRATOR = 'Administrador';
    case USER = 'Usuário';

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Acesso completo ao tenant.',
            self::USER => 'Acesso básico de leitura.',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => Permission::cases(),
            self::USER => [
                Permission::USER_READ,
            ],
        };
    }
}
