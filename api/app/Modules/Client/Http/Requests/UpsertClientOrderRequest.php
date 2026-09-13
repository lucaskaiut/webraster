<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Service\Models\Service;
use App\Modules\Shared\Subscription\Enums\BillingPeriodicity;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertClientOrderRequest extends FormRequest
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
            'due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'periodicity' => ['sometimes', Rule::enum(BillingPeriodicity::class)],
            'next_billing_at' => ['sometimes', 'nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'items.*.vehicle_ids' => ['required', 'array', 'min:1'],
            'items.*.vehicle_ids.*' => ['integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Inclua ao menos um serviço no pedido.',
            'items.min' => 'Inclua ao menos um serviço no pedido.',
            'items.*.vehicle_ids.required' => 'Selecione ao menos um veículo para este serviço.',
            'items.*.vehicle_ids.min' => 'Selecione ao menos um veículo para este serviço.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $resolved = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $resolved[] = $item;

                continue;
            }

            if (isset($item['service_id']) && ! is_numeric($item['service_id'])) {
                $item['service_id'] = Service::query()->where('uuid', $item['service_id'])->value('id');
            }

            if (isset($item['vehicle_ids']) && is_array($item['vehicle_ids'])) {
                $item['vehicle_ids'] = collect($item['vehicle_ids'])
                    ->map(function ($id) {
                        if (is_numeric($id)) {
                            return (int) $id;
                        }

                        return Vehicle::query()->where('uuid', $id)->value('id');
                    })
                    ->filter()
                    ->values()
                    ->all();
            }

            $resolved[] = $item;
        }

        $this->merge(['items' => $resolved]);
    }
}
