<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Models\FinanceContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFinanceReceivableRequest extends FormRequest
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
            'contract_id' => ['required', 'integer', Rule::exists('finance_contracts', 'id')->whereNull('deleted_at')],
            'due_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('contract_id') && ! is_numeric($this->input('contract_id'))) {
            $this->merge([
                'contract_id' => FinanceContract::query()->where('uuid', $this->input('contract_id'))->value('id'),
            ]);
        }
    }
}
