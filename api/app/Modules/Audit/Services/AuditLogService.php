<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        User $user,
        AuditAction $action,
        ?Tenant $selectedTenant = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'selected_tenant_id' => $selectedTenant?->getKey(),
            'action' => $action->value,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Registra um evento de auditoria ligado a uma entidade.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function recordEntity(
        User $user,
        AuditAction $action,
        string $entityType,
        string|int $entityId,
        ?array $details = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'selected_tenant_id' => TenantContext::tenantId(),
            'action' => $action->value,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    public function paginate(int $perPage = 15, ?string $action = null): LengthAwarePaginator
    {
        return AuditLog::query()
            ->where('selected_tenant_id', TenantContext::tenantId())
            ->with('user:id,uuid,name,email')
            ->when(filled($action), fn ($query) => $query->where('action', $action))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }
}
