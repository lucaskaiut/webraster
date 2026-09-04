<?php

namespace App\Modules\Tenant\Http\Resources;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação enxuta para seletor de tenants (usuário master).
 *
 * @mixin Tenant
 */
class AvailableTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'is_home' => (bool) ($this->is_home ?? $this->parent_id === null),
            'is_umbrella' => $this->isUmbrella(),
        ];
    }
}
