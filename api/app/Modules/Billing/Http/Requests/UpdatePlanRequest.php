<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\RecurrenceUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0.01'],
            'recurrence_value' => ['sometimes', 'integer', 'min:1'],
            'recurrence_unit' => ['sometimes', 'string', Rule::enum(RecurrenceUnit::class)],
            'free_trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
