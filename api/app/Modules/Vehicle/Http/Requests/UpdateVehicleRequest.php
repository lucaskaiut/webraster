<?php

namespace App\Modules\Vehicle\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
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
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'client_id' => ['sometimes', 'required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'plate' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('vehicles', 'plate')
                    ->ignore($vehicle->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at')),
            ],
            'chassis' => ['sometimes', 'nullable', 'string', 'max:30'],
            'renavam' => ['sometimes', 'nullable', 'string', 'max:20'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('plate')) {
            $input['plate'] = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('plate')));
        }

        if ($this->filled('chassis')) {
            $input['chassis'] = strtoupper((string) $this->input('chassis'));
        }

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $client = Client::query()->where('uuid', $this->input('client_id'))->first();
            $input['client_id'] = $client?->getKey();
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
