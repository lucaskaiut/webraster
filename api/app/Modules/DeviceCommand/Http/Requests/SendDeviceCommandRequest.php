<?php

namespace App\Modules\DeviceCommand\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendDeviceCommandRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:80'],
            'attributes' => ['sometimes', 'array'],
            'attributes.data' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('attributes') || $this->input('attributes') === null) {
            $this->merge(['attributes' => []]);
        }
    }
}
