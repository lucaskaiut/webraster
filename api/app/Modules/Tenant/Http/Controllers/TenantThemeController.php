<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Requests\UpdateTenantThemeRequest;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Tema do painel (cores e fonte) do tenant ativo. A leitura acompanha a
 * sessão (tenant.theme); aqui fica só a gestão, com ACL própria.
 */
class TenantThemeController extends ApiController
{
    public function show(): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $this->authorize('manageTheme', $tenant);

        return $this->success($tenant->themeSettings());
    }

    public function update(UpdateTenantThemeRequest $request): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $this->authorize('manageTheme', $tenant);

        $theme = $request->theme();
        $isDefault = array_filter($theme, fn ($value) => $value !== null) === [];

        $tenant->forceFill(['theme' => $isDefault ? null : $theme])->save();

        return $this->success($tenant->refresh()->themeSettings(), 'Tema atualizado com sucesso.');
    }
}
