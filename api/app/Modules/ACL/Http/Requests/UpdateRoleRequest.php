<?php

namespace App\Modules\ACL\Http\Requests;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        $role = $this->route('role');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('tenant_id', TenantContext::tenantId())
                    ->ignore($role instanceof Role ? $role->getKey() : null),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ];
    }
}
