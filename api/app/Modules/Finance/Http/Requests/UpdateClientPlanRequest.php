<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Models\FinancePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientPlanRequest extends FormRequest
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
            'plan_id' => ['nullable', 'integer', Rule::exists('finance_plans', 'id')->whereNull('deleted_at')],
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'block_on_overdue' => ['sometimes', 'boolean'],
            'block_after_days' => ['sometimes', 'integer', 'min:0'],
            'next_billing_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plan_id') && ! is_numeric($this->input('plan_id'))) {
            $this->merge([
                'plan_id' => FinancePlan::query()->where('uuid', $this->input('plan_id'))->value('id'),
            ]);
        }
    }
}
