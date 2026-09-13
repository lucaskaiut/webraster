<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
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
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'required',
                'string',
                new CpfOrCnpj,
                Rule::unique('clients', 'document')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at'),
                ),
            ],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'financial_email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'plan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_plans', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('document')) {
            $input['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('document'));
        }

        if ($this->has('zip')) {
            $input['zip'] = (string) preg_replace('/\D+/', '', (string) $this->input('zip'));
        }

        if ($this->has('state')) {
            $input['state'] = strtoupper(trim((string) $this->input('state')));
        }

        if ($this->filled('plan_id') && ! is_numeric($this->input('plan_id'))) {
            $input['plan_id'] = \App\Modules\Finance\Models\FinancePlan::query()
                ->where('uuid', $this->input('plan_id'))
                ->value('id');
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
