<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTechnicianServiceOrderEquipmentRequest extends FormRequest
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
            'equipment_id' => ['required', 'string', 'max:64'],
        ];
    }
}
