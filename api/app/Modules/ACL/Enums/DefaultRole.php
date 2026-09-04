<?php

namespace App\Modules\ACL\Enums;

enum DefaultRole: string
{
    case ADMINISTRATOR = 'Administrador';
    case OPERATOR = 'Operador';
    case CLIENT = 'Cliente';

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Acesso completo ao tenant.',
            self::OPERATOR => 'Acesso operacional para gestão do dia a dia.',
            self::CLIENT => 'Acesso limitado do cliente final.',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => Permission::cases(),
            self::OPERATOR => [
                Permission::USER_READ,
                Permission::TENANT_READ,
                Permission::ROLE_READ,
                Permission::API_TOKEN_READ,
                Permission::WEBHOOK_READ,
                Permission::SUBSCRIPTION_READ,
                Permission::INVOICE_READ,
                Permission::AUDIT_VIEW,
                Permission::ASSISTANT_VIEW,
                Permission::CLIENT_CREATE,
                Permission::CLIENT_READ,
                Permission::CLIENT_UPDATE,
                Permission::CLIENT_DELETE,
                Permission::DRIVER_CREATE,
                Permission::DRIVER_READ,
                Permission::DRIVER_UPDATE,
                Permission::DRIVER_DELETE,
            ],
            self::CLIENT => [
                Permission::SUBSCRIPTION_READ,
                Permission::INVOICE_READ,
                Permission::ASSISTANT_VIEW,
            ],
        };
    }
}
