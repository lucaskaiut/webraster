<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'tenant.domain' => [
                'required', 'string', 'max:255',
                'regex:/^(?=.{1,253}$)((?!-)[a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,63}$/',
                'unique:tenants,domain',
            ],

            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'user.phone' => ['nullable', 'string', 'max:20'],
            'user.document' => ['nullable', 'string', new Cpf],
            'user.password' => ['required', 'string', 'min:8', 'max:255'],

            'plan_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('plans', 'uuid')
                    ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if ($this->has('tenant.document')) {
            $input['tenant']['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('tenant.document'));
        }

        if ($this->has('tenant.domain')) {
            $input['tenant']['domain'] = Str::lower(trim((string) $this->input('tenant.domain')));
        }

        if ($this->filled('user.document')) {
            $input['user']['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('user.document'));
        }

        $this->replace($input);
    }
}
