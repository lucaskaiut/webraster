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
            ])],
            'execution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
