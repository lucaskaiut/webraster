<?php

namespace App\Modules\Notification\Http\Requests;

use App\Modules\Notification\Enums\NotificationSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationLogRequest extends FormRequest
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
            'source' => ['nullable', 'string', Rule::in(NotificationSource::values())],
            'type' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'string', 'uuid'],
            'clicked' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
