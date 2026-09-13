<?php

namespace App\Modules\VehicleData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupVehicleDataRequest extends FormRequest
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
            'plate' => ['required', 'string', 'regex:/^[A-Z0-9]{7}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate')) {
            $this->merge([
                'plate' => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('plate'))),
            ]);
        }
    }
}
