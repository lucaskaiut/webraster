<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeServiceOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ServiceOrderStatus::class)],
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
            'execution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
