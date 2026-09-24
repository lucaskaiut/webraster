<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Contract\Models\Contract;
use App\Modules\Shared\Rules\Cpf;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignClientContractRequest extends FormRequest
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
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'contract_id' => [
                'required',
                Rule::exists('contracts', 'id')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at'),
                ),
            ],
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_cpf' => ['required', 'string', new Cpf],
            'signer_birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('contract_id') && ! is_numeric($this->input('contract_id'))) {
            $this->merge([
                'contract_id' => Contract::query()->where('uuid', $this->input('contract_id'))->value('id'),
            ]);
        }

        if ($this->filled('signer_cpf')) {
            $this->merge([
                'signer_cpf' => preg_replace('/\D+/', '', (string) $this->input('signer_cpf')),
            ]);
        }
    }
}
