<?php

namespace App\Modules\Equipment\Http\Requests;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'imei.regex' => 'O número do equipamento deve conter apenas letras e números, sem espaços.',
            'imei.max' => 'O número do equipamento deve ter no máximo 16 caracteres.',
        ];
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
                'max:16',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('equipments', 'imei')
                    ->ignore($equipment->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at')),
            ],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'line_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'carrier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'stock_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('stocks', 'id')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId()),
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('stock_id')) {
            return;
        }

        if ($this->input('stock_id') === '') {
            $this->merge(['stock_id' => null]);

            return;
        }

        if ($this->filled('stock_id') && ! is_numeric($this->input('stock_id'))) {
            $stock = Stock::query()->where('uuid', (string) $this->input('stock_id'))->first();
            $this->merge(['stock_id' => $stock?->getKey() ?? 'invalid']);
        }
    }
}
