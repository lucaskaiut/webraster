<?php

namespace App\Modules\Finance\Support;

use App\Modules\ACL\Contracts\PermissionRestriction;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Finance\Services\ClientFinancialBlockService;
use App\Modules\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Cliente bloqueado por inadimplência mantém só as permissões do financeiro
 * (e o acesso aos painéis/notificações). Assim menus e telas se ajustam
 * sozinhos a partir das permissões do /auth/me.
 */
class FinancialBlockPermissionRestriction implements PermissionRestriction
{
    /** @var list<Permission> */
    public const ALLOWED = [
        Permission::FINANCE_PORTAL_VIEW,
        Permission::NOTIFICATION_READ,
        Permission::PANEL_WEB_ACCESS,
        Permission::PANEL_SCHEDULING_ACCESS,
    ];

    public function __construct(private readonly ClientFinancialBlockService $blocks) {}

    public function restrict(User $user, Collection $permissions): Collection
    {
        if (! $this->blocks->isBlocked($user)) {
            return $permissions;
        }

        $allowed = array_map(fn (Permission $permission) => $permission->value, self::ALLOWED);

        return $permissions->filter(fn (string $permission) => in_array($permission, $allowed, true));
    }
}
