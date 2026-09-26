<?php

namespace App\Modules\Vehicle\Http\Requests;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\Vehicle\Enums\VehicleTransmission;
use App\Modules\Vehicle\Enums\VehicleType;
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
            'vehicle_type' => ['sometimes', 'nullable', Rule::enum(VehicleType::class)],
            'transmission' => ['sometimes', 'nullable', Rule::enum(VehicleTransmission::class)],
            'odometer' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_speed_kmh' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:300'],
            'speed_hysteresis_percent' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'speed_min_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3600'],
            'average_consumption' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tank_capacity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'crlv_file' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fipe_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'fipe_model_year' => ['sometimes', 'nullable', 'string', 'max:10'],
            'fipe_fuel' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fipe_reference_month' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fipe_value' => ['sometimes', 'nullable', 'string', 'max:50'],
            'fipe_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fipe_brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'fipe_score' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'alert_configs' => ['sometimes', 'array'],
            'alert_configs.*.type' => [
                'required',
                'string',
                'distinct',
                Rule::in(array_map(fn (AlertType $type) => $type->value, AlertType::configurable())),
            ],
            'alert_configs.*.is_enabled' => ['sometimes', 'boolean'],
            'alert_configs.*.notify_in_app' => ['sometimes', 'boolean'],
            'alert_configs.*.notify_monitoring' => ['sometimes', 'boolean'],
            'alert_configs.*.notify_push' => ['sometimes', 'boolean'],
            'alert_configs.*.notify_email' => ['sometimes', 'boolean'],
            'alert_configs.*.alarm_code' => ['sometimes', 'nullable', 'string', 'max:40'],
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
