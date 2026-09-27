<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string'],
            'score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'ai_enabled' => ['sometimes', 'boolean'],
            'source' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
