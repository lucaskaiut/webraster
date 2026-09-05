<?php

namespace App\Modules\Vehicle\Http\Requests;

use App\Modules\Equipment\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEquipmentRequest extends FormRequest
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
            'equipment_id' => ['required', 'integer', Rule::exists('equipments', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('equipment_id') && ! is_numeric($this->input('equipment_id'))) {
            $equipment = Equipment::query()->where('uuid', $this->input('equipment_id'))->first();
            $this->merge(['equipment_id' => $equipment?->getKey()]);
        }
    }
}
