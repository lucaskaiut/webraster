<?php

namespace App\Modules\Equipment\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
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
            'imei' => [
                'required',
                'string',
                'max:20',
                Rule::unique('equipments', 'imei')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at'),
                ),
            ],
            'model' => ['nullable', 'string', 'max:100'],
            'iccid' => ['nullable', 'string', 'max:30'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
