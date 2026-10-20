<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceOrderRequest extends FormRequest
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
            'priority' => ['sometimes', Rule::enum(ServiceOrderPriority::class)],
            'status' => ['sometimes', Rule::in([
                ServiceOrderStatus::OPEN->value,
                ServiceOrderStatus::IN_PROGRESS->value,
            ])],
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'equipment_id' => ['nullable', 'integer', Rule::exists('equipments', 'id')->whereNull('deleted_at')],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after:scheduled_start_at'],
            'address' => ['nullable', 'string', 'max:500'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'installation_location' => ['nullable', 'string', 'max:2000'],
            'installation_location_photo_path' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^uploads\/[A-Za-z0-9][A-Za-z0-9._-]*$/',
            ],
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_amount_cents' => ['required', 'integer', 'min:0', 'max:99999999'],
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

        $items = $this->input('items');

        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (is_array($item) && isset($item['service_id']) && ! is_numeric($item['service_id'])) {
                    $items[$index]['service_id'] = Service::query()
                        ->where('uuid', $item['service_id'])
                        ->value('id');
                }
            }

            $input['items'] = $items;
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
