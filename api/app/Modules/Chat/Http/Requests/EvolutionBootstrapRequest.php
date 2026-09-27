<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvolutionBootstrapRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
