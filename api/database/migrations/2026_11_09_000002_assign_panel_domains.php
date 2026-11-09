<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Domínios já existentes na Vercel e no Cloudflare:
 * - webraster.nuvemax.srv.br → tenant "webraster" (empresa filha);
 * - dash-tracker.nuvemax.srv.br → tenant raiz (umbrella), onde os masters
 *   gerenciam o grupo e trocam entre as filhas.
 *
 * Cada atribuição só ocorre se o tenant existir (raiz: exatamente um) e o
 * domínio estiver livre. Para atribuir manualmente:
 * `php artisan tenants:provision-domain {tenant} --domain={domínio}`.
 */
return new class extends Migration
{
    private const WEBRASTER_DOMAIN = 'webraster.nuvemax.srv.br';

    private const ROOT_DOMAIN = 'dash-tracker.nuvemax.srv.br';

    public function up(): void
    {
        $this->assign(
            DB::table('tenants')->where('identifier', 'webraster')->value('id'),
            self::WEBRASTER_DOMAIN,
        );

        $roots = DB::table('tenants')->whereNull('parent_id')->pluck('id');

        if ($roots->count() === 1) {
            $this->assign($roots->first(), self::ROOT_DOMAIN);
        }
    }

    public function down(): void
    {
        DB::table('tenants')
            ->whereIn('domain', [self::WEBRASTER_DOMAIN, self::ROOT_DOMAIN])
            ->update([
                'domain' => null,
                'domain_status' => null,
                'domain_provisioned_at' => null,
            ]);
    }

    private function assign(mixed $tenantId, string $domain): void
    {
        if ($tenantId === null || DB::table('tenants')->where('domain', $domain)->exists()) {
            return;
        }

        DB::table('tenants')
            ->where('id', $tenantId)
            ->whereNull('domain')
            ->update([
                'domain' => $domain,
                'domain_status' => 'active',
                'domain_error' => null,
                'domain_provisioned_at' => now(),
            ]);
    }
};
