<?php

namespace App\Modules\Notification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'audience' => ['required', 'string', Rule::in(['tenant', 'client', 'users'])],
            'client_id' => [
                'required_if:audience,client',
                'nullable',
                'string',
                Rule::exists('clients', 'uuid')->whereNull('deleted_at'),
            ],
            'user_ids' => ['required_if:audience,users', 'nullable', 'array', 'min:1'],
            'user_ids.*' => ['string', 'uuid'],
            'data' => ['nullable', 'array'],
            'push' => ['nullable', 'boolean'],
        ];
    }
}
