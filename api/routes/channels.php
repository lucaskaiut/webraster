<?php

use App\Modules\Client\Models\Client;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantUuid}.staff', function (User $user, string $tenantUuid): bool {
    if ($user->client_id !== null) {
        return false;
    }

    $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();

    if ($tenant === null) {
        return false;
    }

    return app(MasterTenantAccessService::class)->canAccess($user, $tenant);
});

Broadcast::channel('client.{clientUuid}', function (User $user, string $clientUuid): bool {
    if ($user->client_id === null) {
        return false;
    }

    return Client::query()
        ->whereKey($user->client_id)
        ->where('uuid', $clientUuid)
        ->exists();
});
