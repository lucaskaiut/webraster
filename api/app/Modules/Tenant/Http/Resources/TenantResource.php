<?php

namespace App\Modules\Tenant\Http\Resources;

use App\Modules\Billing\Http\Resources\SubscriptionResource;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'favicon_path' => $this->favicon_path,
            'favicon_url' => $this->faviconUrl(),
            'signature_path' => $this->signature_path,
            'signature_url' => $this->signatureUrl(),
            'late_fine_percent' => $this->late_fine_percent,
            'late_interest_percent' => $this->late_interest_percent,
            'app_name' => $this->app_name,
            'app_icon_path' => $this->app_icon_path,
            'app_icon_url' => $this->appIconUrl(),
            'app_logo_path' => $this->app_logo_path,
            'app_logo_url' => $this->appLogoUrl(),
            'app_primary_color' => $this->app_primary_color,
            'app_secondary_color' => $this->app_secondary_color,
            'require_equipment_loan_acceptance' => (bool) $this->require_equipment_loan_acceptance,
            'equipment_loan_term' => $this->equipment_loan_term,
            'vehicle_alert_defaults' => $this->vehicle_alert_defaults,
            'traccar_server_ip' => config('traccar.server_ip'),
            'traccar_server_dns' => config('traccar.server_dns'),
            'is_umbrella' => $this->isUmbrella(),
            'is_active' => $this->isActive(),
            'modules' => array_map(fn (TenantModule $module) => $module->value, $this->enabledModules()),
            'users_count' => $this->whenCounted('users'),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
