<?php

namespace App\Modules\ApiToken\Http\Requests;

use App\Modules\ACL\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ];
    }
}
