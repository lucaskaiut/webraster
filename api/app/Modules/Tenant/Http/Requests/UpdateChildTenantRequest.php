<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

            'plan_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('plans', 'uuid')
                    ->where(fn ($query) => $query->where('tenant_id', $this->umbrellaTenantId())),
            ],
            'is_complimentary' => ['sometimes', 'boolean'],
            'complimentary_ends_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('is_complimentary') && blank($this->input('plan_id'))) {
                $validator->errors()->add('plan_id', 'Selecione um plano para liberar o acesso cortesia.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if ($this->has('tenant.document')) {
            $input['tenant']['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('tenant.document'));
        }

        if ($this->has('is_complimentary')) {
            $input['is_complimentary'] = filter_var($this->input('is_complimentary'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? $this->boolean('is_complimentary');
        }

        if (array_key_exists('complimentary_ends_at', $input) && $input['complimentary_ends_at'] === '') {
            $input['complimentary_ends_at'] = null;
        }

        if (array_key_exists('plan_id', $input) && $input['plan_id'] === '') {
            $input['plan_id'] = null;
        }

        $this->replace($input);
    }

    private function umbrellaTenantId(): ?int
    {
        $tenantId = $this->user()?->tenant_id;

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
