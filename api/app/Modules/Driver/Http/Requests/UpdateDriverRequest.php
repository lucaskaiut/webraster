<?php

namespace App\Modules\Driver\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Shared\Rules\Cpf;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
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
            'client_id' => ['sometimes', 'required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'vehicle_id' => ['sometimes', 'nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document' => ['sometimes', 'nullable', 'string', new Cpf],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'cnh_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'cnh_expires_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('document')) {
            $input['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('document'));
        }

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $client = Client::query()->where('uuid', $this->input('client_id'))->first();
            $input['client_id'] = $client?->getKey();
        }

        if ($this->filled('vehicle_id') && ! is_numeric($this->input('vehicle_id'))) {
            $vehicle = Vehicle::query()->where('uuid', $this->input('vehicle_id'))->first();
            $input['vehicle_id'] = $vehicle?->getKey();
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
