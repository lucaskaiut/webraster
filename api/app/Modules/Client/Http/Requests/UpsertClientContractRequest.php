<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Contract\Models\Contract;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertClientContractRequest extends FormRequest
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
            'contract_id' => [
                'required',
                Rule::exists('contracts', 'id')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId())->whereNull('deleted_at'),
                ),
            ],
            'valid_until' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('contract_id') && ! is_numeric($this->input('contract_id'))) {
            $this->merge([
                'contract_id' => Contract::query()->where('uuid', $this->input('contract_id'))->value('id'),
            ]);
        }
    }
}
