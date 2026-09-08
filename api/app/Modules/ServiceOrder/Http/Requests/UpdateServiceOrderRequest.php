<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderType;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceOrderRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(ServiceOrderType::class)],
            'priority' => ['sometimes', Rule::enum(ServiceOrderPriority::class)],
            'client_id' => ['sometimes', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'equipment_id' => ['nullable', 'integer', Rule::exists('equipments', 'id')->whereNull('deleted_at')],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after:scheduled_start_at'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'execution_notes' => ['nullable', 'string', 'max:5000'],
            'ignore_schedule_conflict' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $input['client_id'] = Client::query()->where('uuid', $this->input('client_id'))->value('id');
        }

        if ($this->filled('vehicle_id') && ! is_numeric($this->input('vehicle_id'))) {
            $input['vehicle_id'] = Vehicle::query()->where('uuid', $this->input('vehicle_id'))->value('id');
        }

        if ($this->filled('equipment_id') && ! is_numeric($this->input('equipment_id'))) {
            $input['equipment_id'] = Equipment::query()->where('uuid', $this->input('equipment_id'))->value('id');
        }

        if ($this->filled('technician_id') && ! is_numeric($this->input('technician_id'))) {
            $input['technician_id'] = User::query()->where('uuid', $this->input('technician_id'))->value('id');
        }

        foreach (['vehicle_id', 'equipment_id', 'technician_id'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $input[$field] = null;
            }
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
