<?php

namespace App\Modules\Equipment\Http\Requests;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
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
        /** @var Equipment $equipment */
        $equipment = $this->route('equipment');

        return [
            'imei' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('equipments', 'imei')
                    ->ignore($equipment->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at')),
            ],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'iccid' => ['sometimes', 'nullable', 'string', 'max:30'],
            'carrier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
