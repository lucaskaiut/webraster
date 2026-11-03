<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertClientOrderRequest extends FormRequest
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
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'next_billing_at' => ['sometimes', 'nullable', 'date'],
            'plan_id' => ['required', 'integer', Rule::exists('finance_plans', 'id')->whereNull('deleted_at')],
            'vehicle_ids' => ['sometimes', 'array'],
            'vehicle_ids.*' => ['integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Selecione o plano do pedido.',
            'plan_id.exists' => 'Plano inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('plan_id') && ! is_numeric($this->input('plan_id'))) {
            $merge['plan_id'] = FinancePlan::query()->where('uuid', $this->input('plan_id'))->value('id');
        }

        $vehicleIds = $this->input('vehicle_ids');

        if (is_array($vehicleIds)) {
            $merge['vehicle_ids'] = collect($vehicleIds)
                ->map(fn ($id) => is_numeric($id)
                    ? (int) $id
                    : Vehicle::query()->where('uuid', $id)->value('id'))
                ->filter()
                ->values()
                ->all();
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
