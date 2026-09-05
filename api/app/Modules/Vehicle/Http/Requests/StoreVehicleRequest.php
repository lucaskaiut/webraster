<?php

namespace App\Modules\Vehicle\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'plate' => [
                'required',
                'string',
                'max:10',
                Rule::unique('vehicles', 'plate')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at'),
                ),
            ],
            'chassis' => ['nullable', 'string', 'max:30'],
            'renavam' => ['nullable', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
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
