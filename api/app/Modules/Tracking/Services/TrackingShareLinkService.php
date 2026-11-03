<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Client\Models\Scopes\ClientScope;
use App\Modules\Tenant\Models\Scopes\TenantScope;
use App\Modules\Tracking\Models\TrackingShareLink;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingShareLinkService
{
    public function __construct(private readonly TrackingService $tracking) {}

    /**
     * Cria o link. Os veículos são resolvidos no escopo atual (tenant + cliente),
     * então só é possível compartilhar veículos que o usuário enxerga.
     *
     * @param  list<string>  $vehicleUuids
     * @return array{link: TrackingShareLink, token: string}
     */
    public function create(User $user, array $vehicleUuids, int $hours): array
    {
        $vehicles = Vehicle::query()->whereIn('uuid', $vehicleUuids)->get();

        if ($vehicles->count() !== count(array_unique($vehicleUuids))) {
            throw ValidationException::withMessages([
                'vehicle_ids' => ['Um ou mais veículos selecionados não foram encontrados.'],
            ]);
        }

        $hours = min(max($hours, 1), TrackingShareLink::MAX_HOURS);
        $token = Str::random(48);

        $link = DB::transaction(function () use ($user, $vehicles, $hours, $token) {
            $link = TrackingShareLink::query()->create([
                'tenant_id' => $vehicles->first()->tenant_id,
                'created_by' => $user->getKey(),
                'token_hash' => TrackingShareLink::hashToken($token),
                'expires_at' => CarbonImmutable::now()->addHours($hours),
            ]);

            $link->vehicles()->sync($vehicles->modelKeys());

            return $link;
        });

        return ['link' => $link->load('vehicles'), 'token' => $token];
    }

    /**
     * Busca o link pelo token público, sem escopo de tenant (rota pública).
     */
    public function findByToken(string $token): ?TrackingShareLink
    {
        return TrackingShareLink::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('tenant')
            ->where('token_hash', TrackingShareLink::hashToken($token))
            ->first();
    }

    /**
     * @return Collection<int, array{vehicle: Vehicle, position: mixed, online: bool}>
     */
    public function positions(TrackingShareLink $link): Collection
    {
        // Rota pública: sem contexto de tenant/cliente, o escopo é o próprio link.
        $scopes = [TenantScope::class, ClientScope::class];
        $vehicleIds = DB::table('tracking_share_link_vehicle')
            ->where('tracking_share_link_id', $link->getKey())
            ->pluck('vehicle_id');

        $vehicles = Vehicle::query()
            ->withoutGlobalScopes($scopes)
            ->with(['equipment' => fn ($query) => $query->withoutGlobalScopes($scopes)])
            ->where('tenant_id', $link->tenant_id)
            ->whereIn('id', $vehicleIds)
            ->orderBy('plate')
            ->get();

        return $this->tracking->liveFor($vehicles, resolveDevices: false);
    }
}
