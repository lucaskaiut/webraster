<?php

namespace App\Modules\User\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use App\Modules\Stock\Models\Stock;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($user instanceof User ? $user->getKey() : null),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'document' => ['sometimes', 'nullable', 'string', new Cpf],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in([UserType::STAFF->value, UserType::TECHNICIAN->value])],
            'role_ids' => ['sometimes', 'required', 'array', 'min:1'],
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
            'zip' => ['sometimes', 'nullable', 'string', 'max:9'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'complement' => ['sometimes', 'nullable', 'string', 'max:255'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
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
