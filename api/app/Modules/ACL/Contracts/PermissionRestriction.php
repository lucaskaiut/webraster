<?php

namespace App\Modules\ACL\Contracts;

use App\Modules\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Restrição extra sobre as permissões efetivas de um usuário, registrada por
 * módulos do projeto (tag PermissionRestriction::TAG). Permite que regras de
 * domínio (ex.: bloqueio por inadimplência) reduzam o acesso sem que o ACL
 * conheça o domínio.
 */
interface PermissionRestriction
{
    public const TAG = 'acl.permission-restrictions';

    /**
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    public function restrict(User $user, Collection $permissions): Collection;
}
