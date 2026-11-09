<?php

namespace App\Modules\Tenant\Support;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tenant identificado pelo domínio do painel que originou a requisição
 * (tenants.domain: subdomínio provisionado de uma filha ou o domínio do
 * painel principal, que pertence ao umbrella). O host vem do header Origin
 * (enviado pelo navegador em toda chamada cross-origin e não forjável por
 * scripts) ou, na falta dele, do Referer. Requisições sem esses headers
 * (app mobile, integrações) não são associadas a nenhum domínio.
 */
class TenantDomainContext
{
    /** @var array<string, Tenant|null> */
    private array $byHost = [];

    public function tenant(Request $request): ?Tenant
    {
        $host = $this->host($request);

        if ($host === null) {
            return null;
        }

        if (! array_key_exists($host, $this->byHost)) {
            $this->byHost[$host] = $this->findByHost($host);
        }

        return $this->byHost[$host];
    }

    /**
     * Tenant ao qual a requisição fica presa neste domínio. Nulo sem domínio
     * de tenant ou quando o domínio é o do próprio tenant do usuário — aí
     * vale o fluxo normal (ex.: master no domínio do umbrella troca entre as
     * filhas). Nos demais casos, só o tenant do domínio pode ser acessado.
     */
    public function lockedTenant(Request $request, mixed $user): ?Tenant
    {
        $tenant = $this->tenant($request);

        if ($tenant === null) {
            return null;
        }

        if ($user instanceof User && (int) $user->tenant_id === (int) $tenant->getKey()) {
            return null;
        }

        return $tenant;
    }

    public function findByHost(?string $host): ?Tenant
    {
        $host = Str::lower(trim((string) $host, '. '));

        if ($host === '') {
            return null;
        }

        return Tenant::query()->where('domain', $host)->first();
    }

    private function host(Request $request): ?string
    {
        foreach (['Origin', 'Referer'] as $header) {
            $value = $request->headers->get($header);

            if (blank($value) || $value === 'null') {
                continue;
            }

            $host = parse_url((string) $value, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return Str::lower($host);
            }
        }

        return null;
    }
}
