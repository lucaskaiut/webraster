<?php

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Preserva o comportamento atual: todos os perfis existentes continuam
 * acessando os dois painéis. A restrição passa a ser feita removendo a
 * permissão do painel no perfil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::query()->each(fn (Role $role) => $role->grantPermissions(
            Permission::PANEL_WEB_ACCESS,
            Permission::PANEL_SCHEDULING_ACCESS,
        ));
    }

    public function down(): void
    {
        //
    }
};
