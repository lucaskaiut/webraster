<?php

namespace App\Modules\Tenant\Services;

use App\Integrations\Cloudflare\CloudflareClient;
use App\Integrations\Vercel\VercelClient;
use App\Modules\Tenant\Enums\TenantDomainStatus;
use App\Modules\Tenant\Jobs\ProvisionTenantDomainJob;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Subdomínio do painel web por tenant ({identifier}.{base_domain}):
 * cadastro no projeto da Vercel + apontamento CNAME no Cloudflare.
 */
class TenantDomainService
{
    public function __construct(
        private readonly VercelClient $vercel,
        private readonly CloudflareClient $cloudflare,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('tenant_domains.enabled');
    }

    public function baseDomain(): string
    {
        return Str::lower(trim((string) config('tenant_domains.base_domain'), '. '));
    }

    /**
     * Subdomínio derivado do identificador do tenant.
     */
    public function domainFor(Tenant $tenant): string
    {
        $label = Str::limit(Str::slug((string) $tenant->identifier), 63, '');
        $label = trim($label, '-');

        if ($label === '' || ! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
            throw new RuntimeException("Identificador \"{$tenant->identifier}\" não gera um subdomínio válido.");
        }

        if (in_array($label, (array) config('tenant_domains.reserved', []), true)) {
            throw new RuntimeException("O subdomínio \"{$label}\" é reservado.");
        }

        return "{$label}.{$this->baseDomain()}";
    }

    /**
     * Marca o domínio como pendente e agenda o provisionamento (após o commit).
     * Nunca lança: a criação do tenant não depende da infraestrutura externa.
     */
    public function schedule(Tenant $tenant): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $domain = $this->domainFor($tenant);

            if (Tenant::query()->where('domain', $domain)->whereKeyNot($tenant->getKey())->exists()) {
                throw new RuntimeException("O domínio {$domain} já pertence a outro tenant.");
            }
        } catch (Throwable $e) {
            $this->markFailed($tenant, $tenant->domain, $e);

            return;
        }

        $tenant->forceFill([
            'domain' => $domain,
            'domain_status' => TenantDomainStatus::PENDING,
            'domain_error' => null,
        ])->save();

        ProvisionTenantDomainJob::dispatch($tenant)
            ->onQueue((string) config('tenant_domains.queue', 'default'))
            ->afterCommit();
    }

    /**
     * Cadastra o domínio na Vercel e cria/atualiza os registros no Cloudflare.
     * Idempotente: pode ser executado novamente para corrigir ou concluir.
     *
     * @throws Throwable
     */
    public function provision(Tenant $tenant): Tenant
    {
        $domain = $tenant->domain;

        try {
            $domain ??= $this->domainFor($tenant);

            if (! $this->vercel->isConfigured() || ! $this->cloudflare->isConfigured()) {
                throw new RuntimeException('Integrações Vercel/Cloudflare não configuradas.');
            }

            $zoneId = $this->cloudflare->zoneId($this->baseDomain());
            $this->assertNoConflictingRecords($zoneId, $domain);

            $vercelDomain = $this->vercel->addProjectDomain($domain);
            $target = $this->vercel->recommendedCname($domain);

            $this->upsertRecord($zoneId, 'CNAME', $domain, $target, $tenant);

            if (! ($vercelDomain['verified'] ?? false)) {
                $this->createVerificationRecords($zoneId, (array) ($vercelDomain['verification'] ?? []), $tenant);

                $verified = $this->vercel->verifyProjectDomain($domain);

                if (! ($verified['verified'] ?? false)) {
                    throw new RuntimeException("Vercel ainda não verificou {$domain}; nova tentativa em seguida.");
                }
            }
        } catch (Throwable $e) {
            $this->markFailed($tenant, $domain, $e);

            throw $e;
        }

        $tenant->forceFill([
            'domain' => $domain,
            'domain_status' => TenantDomainStatus::ACTIVE,
            'domain_error' => null,
            'domain_provisioned_at' => now(),
        ])->save();

        Log::info('tenant_domain.provisioned', ['tenant_id' => $tenant->getKey(), 'domain' => $domain, 'target' => $target]);

        return $tenant;
    }

    /**
     * Registros de infraestrutura no mesmo nome nunca são sobrescritos.
     * Só um CNAME já apontado para a Vercel pode ser reaproveitado.
     */
    private function assertNoConflictingRecords(string $zoneId, string $domain): void
    {
        foreach ($this->cloudflare->recordsNamed($zoneId, $domain) as $record) {
            $isVercelCname = ($record['type'] ?? null) === 'CNAME'
                && str_contains(Str::lower((string) ($record['content'] ?? '')), 'vercel-dns');

            if (! $isVercelCname) {
                throw new RuntimeException(sprintf(
                    'Já existe o registro DNS %s %s → %s no Cloudflare; provisionamento cancelado para não sobrescrevê-lo.',
                    $record['type'] ?? '?',
                    $domain,
                    $record['content'] ?? '?',
                ));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $challenges  type, domain, value
     */
    private function createVerificationRecords(string $zoneId, array $challenges, Tenant $tenant): void
    {
        foreach ($challenges as $challenge) {
            if (($challenge['type'] ?? null) !== 'TXT' || blank($challenge['domain'] ?? null)) {
                continue;
            }

            $this->upsertRecord($zoneId, 'TXT', (string) $challenge['domain'], (string) $challenge['value'], $tenant);
        }
    }

    private function upsertRecord(string $zoneId, string $type, string $name, string $content, Tenant $tenant): void
    {
        $record = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => 1,
            // A Vercel emite o certificado e precisa receber o tráfego direto.
            'proxied' => false,
            'comment' => sprintf('Vercel - %s (tenant %s)', config('services.vercel.project'), $tenant->uuid),
        ];

        $normalize = fn (mixed $value): string => rtrim(trim((string) $value, '"'), '.');

        $sameType = collect($this->cloudflare->recordsNamed($zoneId, $name))
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === $type);

        // TXT admite vários valores no mesmo nome: só cria o que faltar.
        $existing = $type === 'TXT'
            ? $sameType->first(fn (array $item): bool => $normalize($item['content'] ?? '') === $content)
            : $sameType->first();

        if ($existing === null) {
            $this->cloudflare->createRecord($zoneId, $record);

            return;
        }

        if ($normalize($existing['content'] ?? '') !== $content || ($existing['proxied'] ?? false)) {
            $this->cloudflare->updateRecord($zoneId, (string) $existing['id'], $record);
        }
    }

    private function markFailed(Tenant $tenant, ?string $domain, Throwable $e): void
    {
        // Domínio de outro tenant não é gravado (coluna única).
        if ($domain !== null && Tenant::query()->where('domain', $domain)->whereKeyNot($tenant->getKey())->exists()) {
            $domain = $tenant->getOriginal('domain');
        }

        $tenant->forceFill([
            'domain' => $domain,
            'domain_status' => TenantDomainStatus::FAILED,
            'domain_error' => mb_substr($e->getMessage(), 0, 1000),
        ])->save();

        Log::warning('tenant_domain.failed', [
            'tenant_id' => $tenant->getKey(),
            'domain' => $domain,
            'error' => $e->getMessage(),
        ]);
    }
}
