<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\BillingPeriodicity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancePlanRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'amount_cents' => ['sometimes', 'integer', 'min:0'],
            'periodicity' => ['sometimes', Rule::enum(BillingPeriodicity::class)],
            'device_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
