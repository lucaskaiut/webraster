<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\AsaasEnvironment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAsaasConfigRequest extends FormRequest
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
            'environment' => ['sometimes', Rule::enum(AsaasEnvironment::class)],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'webhook_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
