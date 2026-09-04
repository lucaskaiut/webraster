<?php

namespace App\Modules\User\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user->getKey() : null),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'document' => ['sometimes', 'nullable', 'string', new Cpf],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'role_ids' => ['sometimes', 'required', 'array', 'min:1'],
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
