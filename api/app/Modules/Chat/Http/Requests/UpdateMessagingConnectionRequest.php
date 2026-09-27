<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessagingConnectionRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'instance_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'credentials.webhook_secret' => ['sometimes', 'nullable', 'string'],
            'connection_status' => ['sometimes', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
