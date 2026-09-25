<?php

namespace App\Modules\Notification\Services;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Client\Models\Client;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolve os usuários que devem receber uma notificação (in-app/push).
 */
class NotificationRecipientResolver
{
    /**
     * Mesma regra do AlertDispatcher: usuários do tenant (staff) e do cliente
     * do veículo, com permissão de alerta ou de notificação.
     *
     * @return Collection<int, User>
     */
    public function forVehicle(Vehicle $vehicle): Collection
    {
        return $this->baseQuery()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where(function (Builder $query) use ($vehicle): void {
                $query->whereNull('client_id')
                    ->orWhere('client_id', $vehicle->client_id);
            })
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::ALERT_READ)
                || $user->hasPermission(Permission::NOTIFICATION_READ))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function forClient(Client|int $client): Collection
    {
        $clientId = $client instanceof Client ? $client->getKey() : $client;

        return $this->baseQuery()
            ->where('client_id', $clientId)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::NOTIFICATION_READ))
            ->values();
    }

    /**
     * Usuários internos do tenant (sem vínculo de cliente).
     *
     * @return Collection<int, User>
     */
    public function forTenant(int $tenantId): Collection
    {
        return $this->baseQuery()
            ->where('tenant_id', $tenantId)
            ->whereNull('client_id')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::NOTIFICATION_READ))
            ->values();
    }

    /**
     * @param  list<string>  $userUuids
     * @return Collection<int, User>
     */
    public function forUsers(int $tenantId, array $userUuids): Collection
    {
        return $this->baseQuery()
            ->where('tenant_id', $tenantId)
            ->whereIn('uuid', $userUuids)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::NOTIFICATION_READ))
            ->values();
    }

    /**
     * @return Builder<User>
     */
    private function baseQuery(): Builder
    {
        return User::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at');
    }
}
