<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinancePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignFinanceSubscriptionRequest extends FormRequest
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
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'plan_id' => ['required', 'integer', Rule::exists('finance_plans', 'id')->whereNull('deleted_at')],
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'block_on_overdue' => ['sometimes', 'boolean'],
            'block_after_days' => ['sometimes', 'integer', 'min:0'],
            'next_billing_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $merge['client_id'] = Client::query()->where('uuid', $this->input('client_id'))->value('id');
        }

        if ($this->filled('plan_id') && ! is_numeric($this->input('plan_id'))) {
            $merge['plan_id'] = FinancePlan::query()->where('uuid', $this->input('plan_id'))->value('id');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
