<?php

namespace App\Modules\Tenant\Console\Commands;

use App\Modules\Tenant\Enums\TenantDomainStatus;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantDomainService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Provisiona (ou reprocessa) o subdomínio de tenants filhos de forma síncrona.
 */
class ProvisionTenantDomainCommand extends Command
{
    protected $signature = 'tenants:provision-domain
        {tenants?* : UUID ou identificador dos tenants}
        {--missing : Todos os tenants filhos sem domínio ativo}
        {--domain= : Domínio explícito para um único tenant (padrão: identifier + domínio base)}';

    protected $description = 'Cadastra o subdomínio do tenant na Vercel e cria o apontamento DNS no Cloudflare';

    public function handle(TenantDomainService $domains): int
    {
        $identifiers = (array) $this->argument('tenants');

        if ($identifiers === [] && ! $this->option('missing')) {
            $this->error('Informe os tenants (UUID ou identificador) ou use --missing.');

            return self::INVALID;
        }

        $domain = $this->option('domain') !== null ? Str::lower(trim((string) $this->option('domain'), '. ')) : null;

        if ($domain !== null && (count($identifiers) !== 1 || $this->option('missing'))) {
            $this->error('--domain exige exatamente um tenant (sem --missing).');

            return self::INVALID;
        }

        if ($domain !== null && ! str_ends_with($domain, '.'.$domains->baseDomain())) {
            $this->error("O domínio precisa ser um subdomínio de {$domains->baseDomain()}.");

            return self::INVALID;
        }

        $tenants = Tenant::query()
            ->whereNotNull('parent_id')
            ->when($identifiers !== [], fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereIn('uuid', $identifiers)->orWhereIn('identifier', $identifiers),
            ))
            ->when($this->option('missing'), fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('domain_status')
                    ->orWhere('domain_status', '!=', TenantDomainStatus::ACTIVE->value),
            ))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('Nenhum tenant para provisionar.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($tenants as $tenant) {
            if ($domain !== null && $tenant->domain !== $domain) {
                if (Tenant::query()->where('domain', $domain)->exists()) {
                    $this->error("O domínio {$domain} já pertence a outro tenant.");

                    return self::FAILURE;
                }

                $tenant->forceFill(['domain' => $domain])->save();
            }

            try {
                $domains->provision($tenant);
                $this->info("✓ {$tenant->name}: {$tenant->domainUrl()}");
            } catch (Throwable $e) {
                $failures++;
                $this->error("✗ {$tenant->name}: {$e->getMessage()}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
