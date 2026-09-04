<?php

namespace App\Modules\Tenant\Support;

use App\Modules\Tenant\Models\Tenant;

final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function isResolved(): bool
    {
        return $this->tenant !== null;
    }
}
