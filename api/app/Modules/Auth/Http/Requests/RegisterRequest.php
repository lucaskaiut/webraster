<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Support\PaymentDataRules;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use App\Modules\Shared\Rules\Cpf;
use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
        $plansRequired = $this->publicPlansExist();

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
                Rule::requiredIf($plansRequired),
                'nullable',
                'string',
                'uuid',
                'exists:plans,uuid',
            ],
            'payment_gateway' => [
                'nullable',
                'string',
                Rule::in(app(PaymentGatewayResolver::class)->activeKeys()),
            ],
            ...PaymentDataRules::validationRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (Arr::has($input, 'company') && ! Arr::has($input, 'tenant')) {
            $company = Arr::get($input, 'company', []);
            $user = Arr::get($input, 'user', []);

            $name = (string) ($company['name'] ?? 'empresa');
            $document = (string) preg_replace('/\D+/', '', (string) ($company['document'] ?? ''));
            $phone = (string) ($company['phone'] ?? '');
            $email = (string) ($user['email'] ?? ($company['email'] ?? ''));

            Arr::set($input, 'tenant', [
                'name' => $company['name'] ?? null,
                'document' => $document,
                'phone' => $phone,
                'email' => $email,
                'domain' => $company['domain'] ?? $this->generateDomain($name),
            ]);

            Arr::set($input, 'user.phone', $user['phone'] ?? $phone);
            Arr::set($input, 'user.document', isset($user['document']) && $user['document'] !== ''
                ? preg_replace('/\D+/', '', (string) $user['document'])
                : (strlen($document) === 11 ? $document : null));

            if (Arr::has($input, 'subscription.plan_id')) {
                Arr::set($input, 'plan_id', Arr::get($input, 'subscription.plan_id'));
            }

            if (Arr::has($input, 'payment.method') && filled(Arr::get($input, 'payment.method'))) {
                Arr::set($input, 'payment_gateway', Arr::get($input, 'payment.method'));
                Arr::set($input, 'payment_data', Arr::get($input, 'payment.data', []));
            }
        }

        if (Arr::has($input, 'tenant.document')) {
            Arr::set($input, 'tenant.document', (string) preg_replace('/\D+/', '', (string) Arr::get($input, 'tenant.document')));
        }

        if (Arr::has($input, 'tenant.domain')) {
            Arr::set($input, 'tenant.domain', Str::lower(trim((string) Arr::get($input, 'tenant.domain'))));
        }

        if (Arr::has($input, 'user.document') && filled(Arr::get($input, 'user.document'))) {
            Arr::set($input, 'user.document', (string) preg_replace('/\D+/', '', (string) Arr::get($input, 'user.document')));
        }

        $this->replace($input);
    }

    private function publicPlansExist(): bool
    {
        $rootId = Tenant::query()->orderBy('id')->value('id');

        if ($rootId === null) {
            return false;
        }

        return Plan::query()
            ->withoutTenancy()
            ->where('tenant_id', $rootId)
            ->where('active', true)
            ->exists();
    }

    private function generateDomain(string $name): string
    {
        $slug = Str::slug(Str::ascii($name));
        $slug = $slug !== '' ? $slug : 'empresa';

        return Str::lower($slug.'-'.Str::lower(Str::random(6)).'.nox.app');
    }
}
