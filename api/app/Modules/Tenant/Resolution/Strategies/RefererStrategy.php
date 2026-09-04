<?php

namespace App\Modules\Tenant\Resolution\Strategies;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Resolution\Contracts\ResolutionStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RefererStrategy implements ResolutionStrategy
{
    public function resolve(Request $request): ?Tenant
    {
        $domain = $this->extractDomain($request->headers->get('referer'));

        if (blank($domain)) {
            return null;
        }

        return Tenant::query()
            ->whereIn('domain', array_unique([$domain, Str::after($domain, 'www.')]))
            ->first();
    }

    private function extractDomain(?string $referer): ?string
    {
        if (blank($referer)) {
            return null;
        }

        $referer = trim((string) $referer);

        if (! Str::contains($referer, '://')) {
            $referer = 'https://'.$referer;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) ? Str::lower($host) : null;
    }
}
