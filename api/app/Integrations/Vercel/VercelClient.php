<?php

namespace App\Integrations\Vercel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API da Vercel restrito aos domínios do projeto configurado
 * (services.vercel.project).
 */
class VercelClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.vercel.token')) && filled(config('services.vercel.project'));
    }

    /**
     * Cadastra o domínio no projeto. Se ele já pertence ao projeto, devolve o
     * cadastro existente (operação idempotente).
     *
     * @return array<string, mixed>
     */
    public function addProjectDomain(string $domain): array
    {
        $response = $this->client()->post($this->projectPath('domains'), ['name' => $domain]);

        if ($response->successful()) {
            return (array) $response->json();
        }

        $existing = $this->findProjectDomain($domain);

        if ($existing !== null) {
            return $existing;
        }

        throw VercelException::fromResponse("cadastrar o domínio {$domain}", $response);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProjectDomain(string $domain): ?array
    {
        $response = $this->client()->get($this->projectPath("domains/{$domain}"));

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            throw VercelException::fromResponse("consultar o domínio {$domain}", $response);
        }

        return (array) $response->json();
    }

    /**
     * Solicita a verificação de posse do domínio (registros TXT).
     *
     * @return array<string, mixed>
     */
    public function verifyProjectDomain(string $domain): array
    {
        $response = $this->client()->post($this->projectPath("domains/{$domain}/verify"));

        if ($response->failed()) {
            throw VercelException::fromResponse("verificar o domínio {$domain}", $response);
        }

        return (array) $response->json();
    }

    /**
     * Configuração DNS recomendada pela Vercel para o domínio no projeto.
     *
     * @return array<string, mixed>
     */
    public function domainConfig(string $domain): array
    {
        $response = $this->client()->get("/v6/domains/{$domain}/config", [
            'projectIdOrName' => config('services.vercel.project'),
        ]);

        if ($response->failed()) {
            throw VercelException::fromResponse("obter a configuração DNS de {$domain}", $response);
        }

        return (array) $response->json();
    }

    /**
     * Alvo do CNAME recomendado (rank 1), sem o ponto final.
     */
    public function recommendedCname(string $domain): string
    {
        $recommended = collect($this->domainConfig($domain)['recommendedCNAME'] ?? [])
            ->sortBy('rank')
            ->pluck('value')
            ->first(fn ($value): bool => is_string($value) && $value !== '');

        return rtrim($recommended ?? 'cname.vercel-dns.com', '.');
    }

    public function removeProjectDomain(string $domain): void
    {
        $response = $this->client()->delete($this->projectPath("domains/{$domain}"));

        if ($response->failed() && ! $response->notFound()) {
            throw VercelException::fromResponse("remover o domínio {$domain}", $response);
        }
    }

    private function projectPath(string $path): string
    {
        $project = rawurlencode((string) config('services.vercel.project'));
        $version = str_ends_with($path, 'domains') ? 'v10' : 'v9';

        return "/{$version}/projects/{$project}/{$path}";
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new VercelException('Integração Vercel não configurada (VERCEL_API_TOKEN / VERCEL_PROJECT).');
        }

        $teamId = config('services.vercel.team_id');

        return Http::baseUrl((string) config('services.vercel.base_url'))
            ->withToken((string) config('services.vercel.token'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.vercel.timeout', 15))
            ->withQueryParameters(filled($teamId) ? ['teamId' => $teamId] : []);
    }
}
