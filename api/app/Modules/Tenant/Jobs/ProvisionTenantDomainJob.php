<?php

namespace App\Modules\Tenant\Jobs;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantDomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Provisiona o subdomínio do tenant (Vercel + Cloudflare). As falhas ficam
 * registradas no tenant (domain_status/domain_error) e são retentadas;
 * depois disso, use `php artisan tenants:provision-domain`.
 */
class ProvisionTenantDomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public readonly Tenant $tenant) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 180, 600];
    }

    public function handle(TenantDomainService $domains): void
    {
        $domains->provision($this->tenant);
    }
}
