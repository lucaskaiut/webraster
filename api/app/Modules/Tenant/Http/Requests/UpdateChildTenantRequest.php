<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Enums\TenantModule;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChildTenantRequest extends FormRequest
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
        /** @var Tenant $child */
        $child = $this->route('child');

        return [
            'tenant' => ['required', 'array'],
            'tenant.name' => ['required', 'string', 'max:255'],
            'tenant.document' => ['required', 'string', new CpfOrCnpj],
            'tenant.email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('tenants', 'email')->ignore($child->getKey()),
            ],
            'tenant.phone' => ['required', 'string', 'max:20'],
            'tenant.is_active' => ['sometimes', 'boolean'],
            'tenant.modules' => ['sometimes', 'array'],
            'tenant.modules.*' => ['string', 'distinct', Rule::enum(TenantModule::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if ($this->has('tenant.document')) {
            $input['tenant']['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('tenant.document'));
        }

        $this->replace($input);
    }
}
