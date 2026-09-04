<?php

namespace App\Modules\User\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => ['nullable', 'string', new Cpf],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('document')) {
            $this->merge([
                'document' => (string) preg_replace('/\D+/', '', (string) $this->input('document')),
            ]);
        }
    }
}
