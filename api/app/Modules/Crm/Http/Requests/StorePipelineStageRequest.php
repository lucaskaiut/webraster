<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePipelineStageRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_initial' => ['sometimes', 'boolean'],
            'is_final' => ['sometimes', 'boolean'],
            'is_won' => ['sometimes', 'boolean'],
            'is_lost' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
