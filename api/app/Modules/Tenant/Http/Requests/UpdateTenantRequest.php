<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Alert\Enums\AlertType;
use App\Modules\Shared\Rules\CpfOrCnpj;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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

        $configurableTypes = array_map(
            fn (AlertType $type) => $type->value,
            AlertType::configurable(),
        );

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'identifier' => [
                'sometimes', 'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('tenants', 'identifier')->ignore($tenantId),
            ],
            'document' => ['sometimes', 'required', 'string', new CpfOrCnpj],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('tenants', 'email')->ignore($tenantId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'favicon_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'signature_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_icon_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_primary_color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'app_secondary_color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'vehicle_alert_defaults' => ['sometimes', 'nullable', 'array'],
            'vehicle_alert_defaults.*.type' => ['required', 'string', Rule::in($configurableTypes)],
            'vehicle_alert_defaults.*.alarm_code' => ['nullable', 'string', 'max:40'],
            'vehicle_alert_defaults.*.is_enabled' => ['required', 'boolean'],
            'vehicle_alert_defaults.*.notify_in_app' => ['required', 'boolean'],
            'vehicle_alert_defaults.*.notify_monitoring' => ['required', 'boolean'],
            'vehicle_alert_defaults.*.notify_push' => ['required', 'boolean'],
            'vehicle_alert_defaults.*.notify_email' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('identifier')) {
            $this->merge([
                'identifier' => Str::lower(trim((string) $this->input('identifier'))),
            ]);
        }

        if ($this->has('document')) {
            $this->merge([
                'document' => (string) preg_replace('/\D+/', '', (string) $this->input('document')),
            ]);
        }

        if ($this->has('vehicle_alert_defaults')) {
            $defaults = collect($this->input('vehicle_alert_defaults'))
                ->filter(fn ($item) => is_array($item) && filled($item['type'] ?? null))
                ->map(function (array $item): array {
                    $alarmCode = $item['alarm_code'] ?? null;

                    $item['alarm_code'] = $alarmCode !== null && $alarmCode !== ''
                        ? strtolower(trim((string) $alarmCode))
                        : null;

                    return $item;
                })
                ->values()
                ->all();

            $this->merge(['vehicle_alert_defaults' => $defaults]);
        }
    }
}
