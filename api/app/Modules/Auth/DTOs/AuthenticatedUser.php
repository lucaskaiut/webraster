<?php

namespace App\Modules\Auth\DTOs;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class AuthenticatedUser
{
    /**
     * @param  Collection<int, Tenant>  $availableTenants
     */
    public function __construct(
        public User $user,
        public Tenant $tenant,
        public ?string $token,
        public Collection $availableTenants = new Collection,
    ) {}
}
