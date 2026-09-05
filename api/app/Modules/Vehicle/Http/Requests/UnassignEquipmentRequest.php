<?php

namespace App\Modules\Vehicle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnassignEquipmentRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
