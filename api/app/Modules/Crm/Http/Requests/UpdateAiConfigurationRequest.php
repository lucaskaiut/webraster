<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiConfigurationRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'api_endpoint' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:512'],
            'system_prompt' => ['sometimes', 'nullable', 'string'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
            'temperature' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
