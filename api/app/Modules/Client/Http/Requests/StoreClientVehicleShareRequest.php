<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientVehicleShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                'string',
                Rule::exists('vehicles', 'uuid')->where(
                    fn ($query) => $query
                        ->where('tenant_id', TenantContext::tenantId())
                        ->whereNull('deleted_at'),
                ),
            ],
        ];
    }
}
