<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Enums\BillingPeriodicity;
use App\Modules\Finance\Models\FinancePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinanceContractRequest extends FormRequest
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
            'client_id' => ['sometimes', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'plan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_plans', 'id')->whereNull('deleted_at')],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'periodicity' => ['sometimes', Rule::enum(BillingPeriodicity::class)],
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'amount_cents' => ['sometimes', 'integer', 'min:0'],
            'discount_cents' => ['sometimes', 'integer', 'min:0'],
            'fine_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'interest_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'device_quantity' => ['sometimes', 'integer', 'min:0'],
            'auto_renew' => ['sometimes', 'boolean'],
            'block_on_overdue' => ['sometimes', 'boolean'],
            'block_after_days' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $input['client_id'] = Client::query()->where('uuid', $this->input('client_id'))->value('id');
        }

        if ($this->filled('plan_id') && ! is_numeric($this->input('plan_id'))) {
            $input['plan_id'] = FinancePlan::query()->where('uuid', $this->input('plan_id'))->value('id');
        }

        if ($this->has('plan_id') && $this->input('plan_id') === '') {
            $input['plan_id'] = null;
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
