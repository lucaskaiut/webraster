<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiStageConfigurationRequest extends FormRequest
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
            'objective' => ['sometimes', 'nullable', 'string'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'success_criteria' => ['sometimes', 'nullable', 'string'],
            'allowed_actions' => ['sometimes', 'nullable', 'array'],
            'allowed_actions.*' => ['string'],
            'restricted_actions' => ['sometimes', 'nullable', 'array'],
            'restricted_actions.*' => ['string'],
            'temperature' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:2'],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],
            'max_tokens' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
