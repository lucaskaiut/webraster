<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceSubscriptionRequest extends FormRequest
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
            'next_billing_at' => ['sometimes', 'nullable', 'date'],
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'block_on_overdue' => ['sometimes', 'boolean'],
            'block_after_days' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
