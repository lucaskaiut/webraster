<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\BillingPeriodicity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            'periodicity' => ['sometimes', Rule::enum(BillingPeriodicity::class)],
            'device_limit' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
