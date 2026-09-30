<?php

namespace App\Modules\Tenant\Http\Resources;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dados públicos do tenant para identificação antes do login.
 *
 * @mixin Tenant
 */
class PublicTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'app_name' => $this->app_name,
            'logo_url' => $this->logoUrl(),
            'favicon_url' => $this->faviconUrl(),
            'app_icon_url' => $this->appIconUrl(),
            'app_logo_url' => $this->appLogoUrl(),
            'app_primary_color' => $this->app_primary_color,
            'app_secondary_color' => $this->app_secondary_color,
        ];
    }
}
