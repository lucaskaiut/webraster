<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Enums\TenantModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChildTenantRequest extends FormRequest
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
            'tenant' => ['required', 'array'],
            'tenant.name' => ['required', 'string', 'max:255'],
            'tenant.document' => ['required', 'string', new CpfOrCnpj],
            'tenant.email' => ['required', 'string', 'email', 'max:255', 'unique:tenants,email'],
            'tenant.phone' => ['required', 'string', 'max:20'],
            'tenant.is_active' => ['sometimes', 'boolean'],
            'tenant.modules' => ['sometimes', 'array'],
            'tenant.modules.*' => ['string', 'distinct', Rule::enum(TenantModule::class)],

            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'user.phone' => ['nullable', 'string', 'max:20'],
            'user.document' => ['nullable', 'string', new Cpf],
            'user.password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if ($this->has('tenant.document')) {
            $input['tenant']['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('tenant.document'));
        }

        if ($this->filled('user.document')) {
            $input['user']['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('user.document'));
        }

        $this->replace($input);
    }
}
