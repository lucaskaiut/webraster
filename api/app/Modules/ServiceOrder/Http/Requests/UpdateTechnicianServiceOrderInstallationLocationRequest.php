<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTechnicianServiceOrderInstallationLocationRequest extends FormRequest
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
            'installation_location' => ['nullable', 'string', 'max:2000'],
            'installation_location_photo_path' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^uploads\/[A-Za-z0-9][A-Za-z0-9._-]*$/',
            ],
        ];
    }
}
