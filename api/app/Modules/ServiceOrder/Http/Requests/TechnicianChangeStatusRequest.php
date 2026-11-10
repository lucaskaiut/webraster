<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechnicianChangeStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                ServiceOrderStatus::IN_PROGRESS->value,
                ServiceOrderStatus::COMPLETED->value,
                // O técnico pode frustrar (foi ao local e não conseguiu
                // atender) ou cancelar (o cliente desistiu pelo contato).
                ServiceOrderStatus::FRUSTRATED->value,
                ServiceOrderStatus::CANCELLED->value,
            ])],
            'execution_notes' => ['nullable', 'string', 'max:5000'],
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
            'frustration_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
