<?php

namespace App\Modules\Tenant\Resolution\Contracts;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;

interface ResolutionStrategy
{
    public function resolve(Request $request): ?Tenant;
}
