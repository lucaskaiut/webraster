<?php

namespace App\Modules\User\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:20'],
            'document' => ['nullable', 'string', new Cpf],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'type' => ['sometimes', Rule::in([UserType::STAFF->value, UserType::TECHNICIAN->value])],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())),
            ],
            'technician_active' => ['sometimes', 'boolean'],
            'stock_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('stocks', 'id')->where(
                    fn ($query) => $query->where('tenant_id', TenantContext::tenantId()),
                ),
            ],
            'zip' => ['nullable', 'string', 'max:9'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'work_hours' => ['sometimes', 'array', 'max:21'],
            'work_hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'work_hours.*.starts_at' => ['required', 'date_format:H:i'],
            'work_hours.*.ends_at' => ['required', 'date_format:H:i', 'after:work_hours.*.starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'work_hours.*.ends_at.after' => 'O fim da jornada deve ser posterior ao início.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hours = collect($this->input('work_hours', []))->groupBy('weekday');

            foreach ($hours as $intervals) {
                $sorted = $intervals->sortBy('starts_at')->values();

                for ($i = 1; $i < $sorted->count(); $i++) {
                    if (($sorted[$i]['starts_at'] ?? '') < ($sorted[$i - 1]['ends_at'] ?? '')) {
                        $validator->errors()->add('work_hours', 'Há intervalos sobrepostos na jornada.');

                        return;
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->filled('document')) {
            $data['document'] = (string) preg_replace('/\D+/', '', (string) $this->input('document'));
        }

        if ($this->filled('zip')) {
            $data['zip'] = (string) preg_replace('/\D+/', '', (string) $this->input('zip'));
        }

        if ($this->filled('state')) {
            $data['state'] = mb_strtoupper((string) $this->input('state'));
        }

        if ($this->has('stock_id')) {
            if ($this->input('stock_id') === '') {
                $data['stock_id'] = null;
            } elseif ($this->filled('stock_id') && ! is_numeric($this->input('stock_id'))) {
                $stock = Stock::query()->where('uuid', (string) $this->input('stock_id'))->first();
                $data['stock_id'] = $stock?->getKey() ?? 'invalid';
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}
