<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
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
        $tenantId = TenantContext::tenantId();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document' => ['sometimes', 'required', 'string', new CpfOrCnpj],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('tenants', 'email')->ignore($tenantId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'favicon_path' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document')) {
            $this->merge([
                'document' => (string) preg_replace('/\D+/', '', (string) $this->input('document')),
            ]);
        }
    }
}
