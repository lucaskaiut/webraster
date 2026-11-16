<?php

namespace App\Modules\Notification\Http\Requests;

use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Shared\Support\PerPage;
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
            'per_page' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== PerPage::ALL && (filter_var($value, FILTER_VALIDATE_INT) === false || $value < 1 || $value > 100)) {
                    $fail('A quantidade por página deve ser de 1 a 100 ou "all".');
                }
            }],
        ];
    }
}
