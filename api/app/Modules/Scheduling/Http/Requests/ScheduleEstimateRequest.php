<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Service\Models\Service;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleEstimateRequest extends FormRequest
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
            'client_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:500'],
            'technician_id' => ['nullable', 'integer'],
            'service_order_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'scheduled_start_at' => ['sometimes', 'nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.service_id' => ['required', 'integer'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.integer' => 'Cliente inválido.',
            'technician_id.integer' => 'Técnico inválido.',
            'items.*.service_id.integer' => 'Serviço inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Consultas com escopo de tenant: chaves de outro tenant viram null e falham na validação.
        $input = [];

        foreach ([
            'client_id' => Client::class,
            'technician_id' => User::class,
            'service_order_id' => ServiceOrder::class,
        ] as $field => $model) {
            if ($this->filled($field)) {
                $input[$field] = $model::query()->where('uuid', (string) $this->input($field))->value('id') ?? 'invalid';
            }
        }

        $items = $this->input('items');

        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (is_array($item) && isset($item['service_id'])) {
                    $items[$index]['service_id'] = Service::query()
                        ->where('uuid', (string) $item['service_id'])
                        ->value('id') ?? 'invalid';
                }
            }

            $input['items'] = $items;
        }

        $this->merge($input);
    }
}
