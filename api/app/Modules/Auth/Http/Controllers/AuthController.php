<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\ACL\Enums\Panel;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Http\Resources\RoleResource;
use App\Modules\Auth\DTOs\AuthenticatedUser;
use App\Modules\Auth\DTOs\NewTenantData;
use App\Modules\Auth\DTOs\NewUserData;
use App\Modules\Auth\DTOs\RegisterResult;
use App\Modules\Auth\Http\Requests\ChangePasswordRequest;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Http\Requests\SelectTenantRequest;
use App\Modules\Auth\Http\Requests\StoreUserSignatureRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Resources\AvailableTenantResource;
use App\Modules\Tenant\Http\Resources\TenantResource;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantSwitchService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $service,
        private readonly TenantSwitchService $tenantSwitch,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->service->register(
            NewTenantData::fromArray($request->validated('tenant')),
            NewUserData::fromArray($request->validated('user')),
            $request->validated('plan_id'),
            $request->validated('payment_gateway'),
            $request->validated('payment_data') ?? [],
        );

        return $this->created(
            $this->registerPayload($result),
            'Cadastro realizado com sucesso.',
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('tenant_identifier'),
            Panel::fromRequest($request),
        );

        return $this->success($this->authPayload($result), 'Login realizado com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());

        return $this->success(null, 'Logout realizado com sucesso.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->service->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return $this->success(null, 'Senha alterada com sucesso.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->service->sendPasswordResetLink($request->validated('email'));

        return $this->success(
            null,
            'Se o e-mail informado estiver cadastrado, você receberá as instruções para redefinir a senha.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->service->resetPassword(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('token'),
        );

        return $this->success(null, 'Senha redefinida com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');
        /** @var Tenant|null $tenant */
        $tenant = TenantContext::tenant();

        return $this->success([
            'user' => UserResource::make($user),
            'tenant' => TenantResource::make($tenant),
            'roles' => RoleResource::collection($user->roles),
            'permissions' => $this->effectivePermissions($user->permissionValues(), $tenant),
            // Permissões disponíveis para montar perfis neste tenant.
            'available_permissions' => $this->availablePermissions($tenant),
            'is_master' => (bool) $user->is_master,
            'available_tenants' => AvailableTenantResource::collection(
                $this->service->availableTenantsFor($user),
            ),
        ]);
    }

    public function storeSignature(StoreUserSignatureRequest $request): JsonResponse
    {
        $user = $request->user();
        $previous = $user->signature_path;

        $path = $request->file('image')->store("users/{$user->uuid}", 'local');

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => ['Não foi possível salvar a assinatura.'],
            ]);
        }

        $user->forceFill(['signature_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return $this->success(UserResource::make($user->refresh()), 'Assinatura atualizada com sucesso.');
    }

    public function destroySignature(Request $request): JsonResponse
    {
        $user = $request->user();
        $path = $user->signature_path;

        $user->forceFill(['signature_path' => null])->save();

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        return $this->success(UserResource::make($user->refresh()), 'Assinatura removida com sucesso.');
    }

    public function signatureImage(Request $request): StreamedResponse
    {
        $path = $request->user()->signature_path;

        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response(
            $path,
            'assinatura.png',
            ['Content-Type' => $disk->mimeType($path) ?: 'image/png'],
            'inline',
        );
    }

    public function selectTenant(SelectTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantSwitch->select(
            $request->user(),
            $request->validated('tenant_id'),
        );

        return $this->success([
            'tenant' => AvailableTenantResource::make($tenant),
        ], 'Tenant selecionado com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function registerPayload(RegisterResult $result): array
    {
        return [
            ...$this->authPayload(new AuthenticatedUser(
                user: $result->user,
                tenant: $result->tenant,
                token: $result->token,
                availableTenants: $result->availableTenants,
            )),
            'requires_payment' => $result->requiresPayment,
            'is_trial' => $result->isTrial,
            'trial_days' => $result->trialDays,
            'billing_status' => $result->billingStatus,
            'payment_methods' => $result->paymentMethods,
            'invoice' => $result->invoice
                ? InvoiceResource::make($result->invoice)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(AuthenticatedUser $result): array
    {
        return [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($result->user),
            'tenant' => TenantResource::make($result->tenant),
            'is_master' => (bool) $result->user->is_master,
            'available_tenants' => AvailableTenantResource::collection($result->availableTenants),
        ];
    }

    /**
     * No umbrella, só as de administração do grupo; nos filhos, as
     * liberadas pelos módulos.
     *
     * @return list<string>
     */
    private function availablePermissions(?Tenant $tenant): array
    {
        if ($tenant?->isUmbrella()) {
            return array_map(fn (Permission $permission) => $permission->value, Permission::umbrella());
        }

        return array_values(array_filter(
            Permission::values(),
            fn (string $permission): bool => $tenant?->allowsPermission($permission) ?? true,
        ));
    }

    /**
     * Permissões de plano só são efetivas para tenants umbrella (sem parent_id).
     *
     * @param  Collection<int, string>  $permissions
     * @return list<string>
     */
    private function effectivePermissions(Collection $permissions, ?Tenant $tenant): array
    {
        if ($tenant?->isUmbrella()) {
            return $permissions->values()->all();
        }

        return $permissions
            ->reject(fn (string $permission): bool => str_starts_with($permission, 'plan.'))
            ->values()
            ->all();
    }
}
