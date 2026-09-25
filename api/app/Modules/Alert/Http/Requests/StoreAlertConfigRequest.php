<?php

namespace App\Modules\Alert\Http\Requests;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Client\Models\Client;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAlertConfigRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:150'],
            'type' => ['required', Rule::enum(AlertType::class), Rule::in(array_map(
                fn (AlertType $type) => $type->value,
                AlertType::configurable(),
            ))],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'is_enabled' => ['sometimes', 'boolean'],
            'notify_in_app' => ['sometimes', 'boolean'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_push' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.speed_limit_kmh' => ['sometimes', 'numeric', 'min:1', 'max:300'],
            'settings.min_duration_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'settings.offline_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'settings.battery_threshold' => ['sometimes', 'numeric', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('client_id') && $this->filled('vehicle_id')) {
                $validator->errors()->add('vehicle_id', 'Informe apenas veículo ou cliente, não ambos.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $client = Client::query()->where('uuid', $this->input('client_id'))->first();
            $input['client_id'] = $client?->getKey();
        }

        if ($this->filled('vehicle_id') && ! is_numeric($this->input('vehicle_id'))) {
            $vehicle = Vehicle::query()->where('uuid', $this->input('vehicle_id'))->first();
            $input['vehicle_id'] = $vehicle?->getKey();
        }

        if ($this->has('client_id') && $this->input('client_id') === '') {
            $input['client_id'] = null;
        }

        if ($this->has('vehicle_id') && $this->input('vehicle_id') === '') {
            $input['vehicle_id'] = null;
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
