<?php

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Enums\TenantDomainStatus;
use App\Modules\Tenant\Jobs\ProvisionTenantDomainJob;
use App\Modules\Tenant\Services\TenantDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantDomainProvisioningTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const ZONE = 'zone123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenant_domains.enabled' => true,
            'tenant_domains.base_domain' => 'nuvemax.srv.br',
            'services.vercel.token' => 'vercel-token',
            'services.vercel.team_id' => 'team_1',
            'services.vercel.project' => 'webraster',
            'services.vercel.base_url' => 'https://api.vercel.com',
            'services.cloudflare.token' => 'cf-token',
            'services.cloudflare.zone_id' => self::ZONE,
            'services.cloudflare.base_url' => 'https://api.cloudflare.com/client/v4',
        ]);
    }

    public function test_child_creation_schedules_domain_provisioning(): void
    {
        Queue::fake();

        $umbrella = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', $this->childPayload('Rastro Sistem'))
            ->assertCreated()
            ->assertJsonPath('data.tenant.domain', 'rastro-sistem.nuvemax.srv.br')
            ->assertJsonPath('data.tenant.domain_status', 'pending');

        Queue::assertPushed(
            ProvisionTenantDomainJob::class,
            fn (ProvisionTenantDomainJob $job): bool => $job->tenant->identifier === 'rastro-sistem',
        );
    }

    public function test_child_creation_skips_domain_when_disabled(): void
    {
        config(['tenant_domains.enabled' => false]);
        Queue::fake();

        $umbrella = $this->createTenantWithRoles();
        Sanctum::actingAs($this->createMaster($umbrella));

        $this->postJson('/api/tenant/children', $this->childPayload('Rastro Sistem'))
            ->assertCreated()
            ->assertJsonPath('data.tenant.domain', null);

        Queue::assertNotPushed(ProvisionTenantDomainJob::class);
    }

    public function test_provision_registers_vercel_domain_and_creates_cname(): void
    {
        $this->fakeApis();

        $tenant = $this->createChildTenant($this->createTenantWithRoles(), ['identifier' => 'rastrosistem']);

        app(TenantDomainService::class)->provision($tenant);

        $tenant->refresh();
        $this->assertSame('rastrosistem.nuvemax.srv.br', $tenant->domain);
        $this->assertSame(TenantDomainStatus::ACTIVE, $tenant->domain_status);
        $this->assertNull($tenant->domain_error);
        $this->assertNotNull($tenant->domain_provisioned_at);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://api.vercel.com/v10/projects/webraster/domains?teamId=team_1')
            && $request['name'] === 'rastrosistem.nuvemax.srv.br'
            && $request->hasHeader('Authorization', 'Bearer vercel-token'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.cloudflare.com/client/v4/zones/'.self::ZONE.'/dns_records'
            && $request['type'] === 'CNAME'
            && $request['name'] === 'rastrosistem.nuvemax.srv.br'
            && $request['content'] === 'abc123.vercel-dns-017.com'
            && $request['proxied'] === false);
    }

    public function test_provision_is_idempotent_when_cname_already_points_to_vercel(): void
    {
        $this->fakeApis(existingRecords: [[
            'id' => 'rec1', 'type' => 'CNAME', 'name' => 'rastrosistem.nuvemax.srv.br',
            'content' => 'abc123.vercel-dns-017.com', 'proxied' => false,
        ]]);

        $tenant = $this->createChildTenant($this->createTenantWithRoles(), ['identifier' => 'rastrosistem']);

        app(TenantDomainService::class)->provision($tenant);

        $this->assertSame(TenantDomainStatus::ACTIVE, $tenant->refresh()->domain_status);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'cloudflare')
            && in_array($request->method(), ['POST', 'PATCH'], true));
    }

    public function test_provision_never_overwrites_existing_infrastructure_record(): void
    {
        $this->fakeApis(existingRecords: [[
            'id' => 'rec1', 'type' => 'A', 'name' => 'frota.nuvemax.srv.br',
            'content' => '185.194.219.192', 'proxied' => false,
        ]]);

        $tenant = $this->createChildTenant($this->createTenantWithRoles(), ['identifier' => 'frota']);

        try {
            app(TenantDomainService::class)->provision($tenant);
            $this->fail('Provisionamento deveria falhar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Já existe o registro DNS A', $e->getMessage());
        }

        $tenant->refresh();
        $this->assertSame(TenantDomainStatus::FAILED, $tenant->domain_status);
        $this->assertNotNull($tenant->domain_error);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'vercel'));
    }

    public function test_provision_creates_verification_records_when_vercel_requires(): void
    {
        $this->fakeApis(vercelVerified: false);

        $tenant = $this->createChildTenant($this->createTenantWithRoles(), ['identifier' => 'rastrosistem']);

        app(TenantDomainService::class)->provision($tenant);

        $this->assertSame(TenantDomainStatus::ACTIVE, $tenant->refresh()->domain_status);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), 'dns_records')
            && $request['type'] === 'TXT'
            && $request['name'] === '_vercel.nuvemax.srv.br'
            && $request['content'] === 'vc-domain-verify=rastrosistem.nuvemax.srv.br,xyz');

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            parse_url($request->url(), PHP_URL_PATH),
            '/v9/projects/webraster/domains/rastrosistem.nuvemax.srv.br/verify',
        ));
    }

    public function test_reserved_subdomain_fails_without_calling_apis(): void
    {
        Http::fake();
        Queue::fake();

        $tenant = $this->createChildTenant($this->createTenantWithRoles(), ['identifier' => 'agendamento']);

        app(TenantDomainService::class)->schedule($tenant);

        $this->assertSame(TenantDomainStatus::FAILED, $tenant->refresh()->domain_status);
        Queue::assertNotPushed(ProvisionTenantDomainJob::class);
        Http::assertNothingSent();
    }

    public function test_domain_taken_by_another_tenant_fails_without_breaking_creation(): void
    {
        Queue::fake();

        $umbrella = $this->createTenantWithRoles();
        $this->createChildTenant($umbrella, ['identifier' => 'outra', 'domain' => 'rastro.nuvemax.srv.br']);
        $tenant = $this->createChildTenant($umbrella, ['identifier' => 'rastro']);

        app(TenantDomainService::class)->schedule($tenant);

        $tenant->refresh();
        $this->assertSame(TenantDomainStatus::FAILED, $tenant->domain_status);
        $this->assertNull($tenant->domain);
        Queue::assertNotPushed(ProvisionTenantDomainJob::class);
    }

    public function test_command_provisions_missing_domains(): void
    {
        $this->fakeApis();

        $umbrella = $this->createTenantWithRoles();
        $tenant = $this->createChildTenant($umbrella, ['identifier' => 'rastrosistem']);

        $this->artisan('tenants:provision-domain', ['--missing' => true])->assertSuccessful();

        $this->assertSame(TenantDomainStatus::ACTIVE, $tenant->refresh()->domain_status);
        $this->assertNull($umbrella->refresh()->domain);
    }

    /**
     * @param  list<array<string, mixed>>  $existingRecords
     */
    private function fakeApis(array $existingRecords = [], bool $vercelVerified = true): void
    {
        Http::fake([
            'api.vercel.com/v10/projects/webraster/domains*' => Http::response([
                'name' => 'rastrosistem.nuvemax.srv.br',
                'verified' => $vercelVerified,
                'verification' => $vercelVerified ? [] : [[
                    'type' => 'TXT',
                    'domain' => '_vercel.nuvemax.srv.br',
                    'value' => 'vc-domain-verify=rastrosistem.nuvemax.srv.br,xyz',
                    'reason' => 'pending_domain_verification',
                ]],
            ]),
            'api.vercel.com/v9/projects/webraster/domains/*/verify*' => Http::response(['verified' => true]),
            'api.vercel.com/v6/domains/*/config*' => Http::response([
                'recommendedCNAME' => [
                    ['rank' => 2, 'value' => 'cname.vercel-dns.com.'],
                    ['rank' => 1, 'value' => 'abc123.vercel-dns-017.com.'],
                ],
            ]),
            'api.cloudflare.com/client/v4/zones/'.self::ZONE.'/dns_records?*' => Http::response([
                'success' => true,
                'result' => $existingRecords,
            ]),
            'api.cloudflare.com/client/v4/zones/'.self::ZONE.'/dns_records' => Http::response([
                'success' => true,
                'result' => ['id' => 'new'],
            ]),
            'api.cloudflare.com/client/v4/zones/'.self::ZONE.'/dns_records/*' => Http::response([
                'success' => true,
                'result' => ['id' => 'rec1'],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function childPayload(string $name): array
    {
        return [
            'tenant' => [
                'name' => $name,
                'document' => '04.252.011/0001-10',
                'email' => 'contato@nova.com',
                'phone' => '41999999999',
            ],
            'user' => [
                'name' => 'Admin Nova',
                'email' => 'admin@nova.com',
                'password' => '12345678',
            ],
        ];
    }
}
