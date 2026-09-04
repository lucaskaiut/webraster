<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\RecurrenceUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'recurrence_value' => ['required', 'integer', 'min:1'],
            'recurrence_unit' => ['required', 'string', Rule::enum(RecurrenceUnit::class)],
            'free_trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
