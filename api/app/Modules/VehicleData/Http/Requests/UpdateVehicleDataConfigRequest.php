<?php

namespace App\Modules\VehicleData\Http\Requests;

use App\Modules\VehicleData\Support\PlateLookupResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleDataConfigRequest extends FormRequest
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
        $keys = app(PlateLookupResolver::class)->keys();

        return [
            'provider' => ['required', 'string', Rule::in($keys)],
            'is_active' => ['sometimes', 'boolean'],
            'credentials' => ['required', 'array'],
        ];
    }
}
