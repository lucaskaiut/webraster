<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Models\FinanceSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFinanceBillingRequest extends FormRequest
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
            'subscription_id' => ['required', 'integer', Rule::exists('finance_subscriptions', 'id')->whereNull('deleted_at')],
            'due_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('subscription_id') && ! is_numeric($this->input('subscription_id'))) {
            $this->merge([
                'subscription_id' => FinanceSubscription::query()
                    ->where('uuid', $this->input('subscription_id'))
                    ->value('id'),
            ]);
        }
    }
}
