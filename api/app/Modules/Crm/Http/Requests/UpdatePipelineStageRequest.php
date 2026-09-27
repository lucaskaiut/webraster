<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePipelineStageRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_initial' => ['sometimes', 'boolean'],
            'is_final' => ['sometimes', 'boolean'],
            'is_won' => ['sometimes', 'boolean'],
            'is_lost' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
