<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPipelineStagesRequest extends FormRequest
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
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['required', 'uuid'],
        ];
    }
}
