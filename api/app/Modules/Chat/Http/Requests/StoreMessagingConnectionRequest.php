<?php

namespace App\Modules\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessagingConnectionRequest extends FormRequest
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
            'provider' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'instance_name' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'credentials.webhook_secret' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
