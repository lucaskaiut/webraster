<?php

namespace Tests\Feature\ContractEvidence;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\Contract\Models\Contract;
use App\Modules\ContractEvidence\Exceptions\ImmutableEvidenceException;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Models\ContractSigningSession;
use App\Modules\ContractEvidence\Services\SignedDocumentService;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;
use ZipArchive;

class ContractSigningEvidenceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const CPF = '52998224725';

    private Tenant $tenant;

    private Client $client;

    private Contract $contract;

    private User $clientUser;

    /** @var array{key_id: string, secret_key: string, public_key: string} */
    private array $key;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->key = EvidenceKeyring::generate();
        $this->useKey($this->key, [$this->key]);

        [, $this->tenant] = $this->createOperationalChild();
        $this->client = Client::factory()->for($this->tenant)->create(['document' => self::CPF]);
        $this->contract = Contract::factory()->forTenant($this->tenant)->create([
            'name' => 'Contrato de rastreamento',
            'body' => '<p>Contrato de {{NOME_CLIENTE}}</p><p>{{ASSINATURA_CLIENTE}}</p>',
        ]);

        $this->assignContract($this->contract);

        $this->clientUser = $this->createClient($this->tenant, ['client_id' => $this->client->getKey()]);
    }

    // ---------------------------------------------------------------------
    // Integridade
    // ---------------------------------------------------------------------

    public function test_signed_pdf_hash_matches_preserved_bytes_and_downloads_are_identical(): void
    {
        $signature = $this->signThroughApp()->json('data.signature');

        $record = ContractSignature::query()->where('uuid', $signature['id'])->firstOrFail();
        $bytes = Storage::disk('local')->get($record->document_path);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $signature['document_sha256']);
        $this->assertSame('protected', $signature['evidence_status']);

        $portal = $this->get('/api/contract/portal/document');
        $portal->assertOk();
        $this->assertSame($bytes, $portal->streamedContent());

        $this->actingAsAdmin();
        $first = $this->get("/api/clients/{$this->client->uuid}/contract/pdf")->streamedContent();
        $second = $this->get("/api/clients/{$this->client->uuid}/contract/pdf")->streamedContent();

        $this->assertSame($bytes, $first);
        $this->assertSame($first, $second);
        $this->assertSame($signature['document_sha256'], $this->contractRow()->document_hash);

        $this->verify($signature['id'])->assertJsonPath('data.status', 'valid');
    }

    public function test_altered_pdf_is_detected(): void
    {
        $record = $this->signedRecord();

        Storage::disk('local')->put($record->document_path, Storage::disk('local')->get($record->document_path).'%tamper');

        $report = $this->verify($record->uuid)->json('data');

        $this->assertSame('integrity_failure', $report['status']);
        $this->assertSame('integrity_failure', $this->check($report, 'artifact:signed_document'));
    }

    public function test_replaced_signature_image_is_detected(): void
    {
        $record = $this->signedRecord();

        Storage::disk('local')->put($record->signature_image_path, $this->png(30, 12));

        $report = $this->verify($record->uuid)->json('data');

        $this->assertSame('integrity_failure', $report['status']);
        $this->assertSame('integrity_failure', $this->check($report, 'artifact:signature_image'));
    }

    public function test_missing_artifact_is_reported_as_missing(): void
    {
        $record = $this->signedRecord();

        Storage::disk('local')->delete($record->document_path);

        $report = $this->verify($record->uuid)->json('data');

        $this->assertSame('missing', $report['status']);
        $this->assertSame('missing', $this->check($report, 'artifact:signed_document'));
    }

    public function test_altered_manifest_invalidates_signature_and_database_blocks_updates(): void
    {
        $record = $this->signedRecord();

        try {
            DB::table('contract_signatures')->where('id', $record->id)->update(['manifest' => '{}']);
            $this->fail('O banco deveria bloquear a alteração do manifesto.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('evidencia imutavel', $e->getMessage());
        }

        $package = $this->exportPackage($record);
        $this->assertStringContainsString('"name":"Maria da Silva"', $record->manifest);

        $tampered = $this->rewriteZip($package, 'manifest.json', str_replace(
            '"name":"Maria da Silva"',
            '"name":"Maria da Silvb"',
            $record->manifest,
        ));

        $this->artisan('contract-evidence:verify', ['--package' => $tampered])->assertExitCode(1);
    }

    public function test_model_layer_rejects_changes_and_deletion_of_evidence(): void
    {
        $record = $this->signedRecord();

        try {
            $record->update(['document_sha256' => str_repeat('0', 64)]);
            $this->fail('Alteração deveria ser rejeitada.');
        } catch (ImmutableEvidenceException) {
        }

        try {
            $record->delete();
            $this->fail('Exclusão deveria ser rejeitada.');
        } catch (ImmutableEvidenceException) {
        }

        $this->expectException(ImmutableEvidenceException::class);
        ContractEvidenceEvent::query()->firstOrFail()->update(['type' => 'x']);
    }

    public function test_wrong_or_unknown_key_does_not_validate(): void
    {
        $record = $this->signedRecord();
        $other = EvidenceKeyring::generate();

        // Mesmo key_id apontando para outra chave pública: assinatura inválida.
        config(['contract_evidence.signing.private_key' => null]);
        config(['contract_evidence.signing.trusted_public_keys' => $this->key['key_id'].':'.$other['public_key']]);

        $report = $this->verify($record->uuid)->json('data');
        $this->assertSame('integrity_failure', $this->check($report, 'manifest_signature'));

        // Chave fora do chaveiro: inconclusivo (não confirma nem refuta).
        config(['contract_evidence.signing.trusted_public_keys' => $other['key_id'].':'.$other['public_key']]);

        $report = $this->verify($record->uuid)->json('data');
        $this->assertSame('inconclusive', $this->check($report, 'manifest_signature'));
        $this->assertSame('inconclusive', $report['status']);
    }

    public function test_old_manifests_remain_valid_after_key_rotation(): void
    {
        $old = $this->signedRecord();
        $new = EvidenceKeyring::generate();

        $this->useKey($new, [$this->key, $new]);

        $this->assertSame('valid', $this->verify($old->uuid)->json('data.status'));

        $this->actingAsAdmin();
        $this->putJson("/api/clients/{$this->client->uuid}/contract/signature", ['signature_status' => 'pending'])->assertOk();

        $signature = $this->signThroughApp()->json('data.signature');

        $this->assertSame($this->key['key_id'], $old->fresh()->key_id);

        $this->assertSame($new['key_id'], $signature['key_id']);
        $this->assertSame('valid', $this->verify($signature['id'])->json('data.status'));
    }

    // ---------------------------------------------------------------------
    // Autorização e vínculo
    // ---------------------------------------------------------------------

    public function test_another_client_cannot_complete_session(): void
    {
        $session = $this->openSigningSession();

        $intruderClient = Client::factory()->for($this->tenant)->create();
        $this->clientUser = $this->createClient($this->tenant, ['client_id' => $intruderClient->getKey()]);

        $this->complete($session)->assertNotFound()->assertJsonPath('code', 'session_not_found');

        $this->assertSame('pending', $this->contractRow()->signature_status->value);
    }

    public function test_user_from_another_tenant_cannot_complete_session(): void
    {
        $session = $this->openSigningSession();

        [, $otherTenant] = $this->createOperationalChild();
        $otherClient = Client::factory()->for($otherTenant)->create();
        $this->clientUser = $this->createClient($otherTenant, ['client_id' => $otherClient->getKey()]);

        $this->complete($session)->assertNotFound();

        $this->assertSame(0, ContractSignature::withoutGlobalScopes()->count());
    }

    public function test_staff_user_cannot_open_signing_session(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/contract/portal/signing-sessions')->assertForbidden();
    }

    public function test_expired_session_cannot_be_completed(): void
    {
        $session = $this->openSigningSession();

        $this->travel(31)->minutes();

        $this->complete($session)->assertStatus(410)->assertJsonPath('code', 'session_expired');

        $this->assertSame('expired', DB::table('contract_signing_sessions')->where('uuid', $session['id'])->value('status'));
        $this->assertSame('pending', $this->contractRow()->signature_status->value);
        $this->assertDatabaseHas('contract_evidence_events', ['type' => 'signing_session_expired']);
    }

    public function test_session_is_revoked_when_document_changes(): void
    {
        $session = $this->openSigningSession();

        $this->contract->update(['body' => '<p>Nova cláusula</p><p>{{ASSINATURA_CLIENTE}}</p>']);
        $this->assignContract($this->contract);

        $this->assertSame(2, $this->contractRow()->document_version);

        $this->complete($session)->assertStatus(410)->assertJsonPath('code', 'session_revoked');

        $this->assertDatabaseHas('contract_evidence_events', ['type' => 'document_version_changed']);
        $this->assertDatabaseHas('contract_signing_sessions', ['uuid' => $session['id'], 'revoked_reason' => 'document_changed']);
    }

    public function test_identity_and_contract_supplied_by_app_are_ignored(): void
    {
        $otherClient = Client::factory()->for($this->tenant)->create();
        $otherContract = Contract::factory()->forTenant($this->tenant)->create();

        $session = $this->openSigningSession();

        $this->complete($session, [
            'client_id' => $otherClient->uuid,
            'contract_id' => $otherContract->uuid,
            'user_id' => 999,
        ])->assertOk();

        $record = ContractSignature::query()->firstOrFail();

        $this->assertSame($this->client->getKey(), (int) $record->client_id);
        $this->assertSame($this->clientUser->getKey(), (int) $record->user_id);
        $this->assertSame($this->contract->getKey(), (int) $record->contract_id);
        $this->assertNull(ClientContract::query()->where('client_id', $otherClient->getKey())->first());
    }

    public function test_session_of_other_contract_cannot_be_used_after_contract_swap(): void
    {
        $session = $this->openSigningSession();

        $another = Contract::factory()->forTenant($this->tenant)->create();
        $this->assignContract($another);

        $this->complete($session)->assertStatus(410)->assertJsonPath('code', 'session_revoked');
        $this->assertSame(0, ContractSignature::query()->count());
    }

    // ---------------------------------------------------------------------
    // Fluxo
    // ---------------------------------------------------------------------

    public function test_presented_version_and_consent_are_bound_to_signature(): void
    {
        $session = $this->openSigningSession();

        $this->assertSame(hash('sha256', $session['document']['html']), $session['document']['sha256']);
        $this->assertSame(hash('sha256', $session['consent']['text']), $session['consent']['sha256']);

        $signature = $this->complete($session)->assertOk()->json('data.signature');
        $record = ContractSignature::query()->where('uuid', $signature['id'])->firstOrFail();
        $manifest = json_decode($record->manifest, true);

        $this->assertSame($session['document']['sha256'], $record->presented_document_sha256);
        $this->assertSame($session['document']['sha256'], $manifest['artifacts']['presented_document']['sha256']);
        $this->assertSame($session['document']['html'], Storage::disk('local')->get($record->presented_document_path));
        $this->assertTrue($manifest['consent']['explicit']);
        $this->assertSame($session['consent']['text'], $manifest['consent']['text']);
        $this->assertSame($session['consent']['version'], $record->consent_version);
        $this->assertSame('1.4.0', $manifest['context']['declared']['app_version']);
        $this->assertSame('127.0.0.1', $manifest['context']['observed']['ip']);
        $this->assertTrue($record->cpf_matches_client);
    }

    public function test_consent_must_be_explicit_and_match_presented_text(): void
    {
        $session = $this->openSigningSession();

        $this->complete($session, ['consent_accepted' => '0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_accepted']);

        $this->complete($session, ['consent_sha256' => str_repeat('a', 64)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'consent_required');

        $this->complete($session, ['document_sha256' => str_repeat('b', 64)])
            ->assertConflict()
            ->assertJsonPath('code', 'document_changed');

        $this->assertSame(2, ContractEvidenceEvent::query()->where('type', 'signing_rejected')->count());
        $this->assertSame(0, ContractSignature::query()->count());

        $this->complete($session)->assertOk();
    }

    public function test_duplicate_request_is_idempotent_and_conflicting_resubmission_is_rejected(): void
    {
        $session = $this->openSigningSession();
        $image = $this->png();

        $first = $this->complete($session, [], $image)->assertOk();
        $second = $this->complete($session, [], $image)->assertOk();

        $this->assertFalse($first->json('data.idempotent'));
        $this->assertTrue($second->json('data.idempotent'));
        $this->assertSame($first->json('data.signature.id'), $second->json('data.signature.id'));
        $this->assertSame(1, ContractSignature::query()->count());

        $this->complete($session, [], $this->png(40, 20))->assertConflict()->assertJsonPath('code', 'already_completed');
    }

    public function test_concurrent_completion_is_rejected_and_recovered_by_reconciliation(): void
    {
        $session = $this->openSigningSession();

        ContractSigningSession::query()->where('uuid', $session['id'])->update(['status' => 'completing']);

        $this->complete($session)->assertConflict()->assertJsonPath('code', 'in_progress');

        $this->travel(6)->minutes();
        $this->artisan('contract-evidence:reconcile')->assertSuccessful();

        $this->assertSame('open', DB::table('contract_signing_sessions')->where('uuid', $session['id'])->value('status'));
        $this->assertDatabaseHas('contract_evidence_events', ['type' => 'signing_session_recovered']);

        $this->complete($session)->assertOk();
    }

    public function test_persistence_failure_never_reports_false_success(): void
    {
        $session = $this->openSigningSession();

        $real = $this->app->make(SignedDocumentService::class);

        $this->mock(SignedDocumentService::class, function ($mock) use ($real): void {
            $mock->shouldReceive('seal')->once()->andThrow(new RuntimeException('disco cheio'));
            $mock->shouldReceive('seal')->andReturnUsing(fn (...$args) => $real->seal(...$args));
            $mock->shouldReceive('presentedHtml')->andReturnUsing(fn (...$args) => $real->presentedHtml(...$args));
        });

        $this->complete($session)->assertStatus(503)->assertJsonPath('code', 'persistence_failed');

        $this->assertSame('pending', $this->contractRow()->signature_status->value);
        $this->assertSame(0, ContractSignature::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('contract-evidence/'.$this->tenant->getKey().'/'.$this->contractRow()->uuid.'/signatures'));

        $row = ContractSigningSession::query()->where('uuid', $session['id'])->firstOrFail();
        $this->assertSame('open', $row->status->value);
        $this->assertSame(1, $row->failure_count);
        $this->assertDatabaseHas('contract_evidence_events', ['type' => 'signing_failed', 'result' => 'failure']);

        $this->complete($session)->assertOk();
        $this->assertSame('signed', $this->contractRow()->signature_status->value);
    }

    public function test_missing_signing_key_keeps_evidence_pending_until_reconciled(): void
    {
        config(['contract_evidence.signing.private_key' => null, 'contract_evidence.signing.trusted_public_keys' => '']);

        $signature = $this->signThroughApp()->json('data.signature');

        $this->assertSame('pending', $signature['evidence_status']);
        $this->assertSame('signed', $this->contractRow()->signature_status->value);

        $this->actingAsAdmin();
        $this->getJson("/api/clients/{$this->client->uuid}/contract")->assertJsonPath('data.evidence_level', 'pending');
        $this->assertSame('inconclusive', $this->verify($signature['id'])->json('data.status'));

        $this->useKey($this->key, [$this->key]);
        $this->artisan('contract-evidence:reconcile')->assertSuccessful();

        $this->assertSame('protected', DB::table('contract_signatures')->where('uuid', $signature['id'])->value('evidence_status'));
        $this->assertSame('valid', $this->verify($signature['id'])->json('data.status'));
    }

    public function test_signed_document_is_frozen_when_same_contract_is_reassigned(): void
    {
        $this->signThroughApp();
        $body = $this->contractRow()->body;

        $this->contract->update(['body' => '<p>Texto alterado depois da assinatura</p>']);
        $this->assignContract($this->contract);

        $this->assertSame($body, $this->contractRow()->body);
        $this->assertSame('signed', $this->contractRow()->signature_status->value);
    }

    public function test_cannot_sign_twice(): void
    {
        $this->signThroughApp();

        $this->postJson('/api/contract/portal/signing-sessions')->assertConflict()->assertJsonPath('code', 'already_signed');
    }

    public function test_declared_cpf_divergent_from_client_record_is_flagged(): void
    {
        $session = $this->openSigningSession();

        $this->complete($session, ['signer_cpf' => Document::fakeCpf()])->assertOk()
            ->assertJsonPath('data.signature.cpf_matches_client', false);
    }

    public function test_legacy_endpoint_records_signature_without_explicit_consent(): void
    {
        Sanctum::actingAs($this->clientUser);

        $this->post("/api/clients/{$this->client->uuid}/contract/signature", [
            'image' => UploadedFile::fake()->createWithContent('assinatura.png', $this->png()),
            'contract_id' => $this->contract->uuid,
            'signer_name' => 'Maria da Silva',
            'signer_cpf' => self::CPF,
            'signer_birth_date' => '1990-05-20',
        ], ['Accept' => 'application/json'])->assertOk();

        $record = ContractSignature::query()->firstOrFail();

        $this->assertSame('legacy_app_signature', $record->method->value);
        $this->assertFalse($record->consent_explicit);
        $this->assertSame('valid', $this->verify($record->uuid)->json('data.status'));
    }

    public function test_legacy_endpoint_can_be_disabled(): void
    {
        config(['contract_evidence.allow_legacy_signing' => false]);

        Sanctum::actingAs($this->clientUser);

        $this->post("/api/clients/{$this->client->uuid}/contract/signature", [
            'image' => UploadedFile::fake()->createWithContent('assinatura.png', $this->png()),
            'contract_id' => $this->contract->uuid,
            'signer_name' => 'Maria da Silva',
            'signer_cpf' => self::CPF,
            'signer_birth_date' => '1990-05-20',
        ], ['Accept' => 'application/json'])->assertStatus(426)->assertJsonPath('code', 'app_update_required');
    }

    public function test_marking_pending_supersedes_without_destroying_evidence(): void
    {
        $record = $this->signedRecord();

        $this->actingAsAdmin();
        $this->putJson("/api/clients/{$this->client->uuid}/contract/signature", ['signature_status' => 'pending'])->assertOk();

        $record->refresh();

        $this->assertNotNull($record->superseded_at);
        $this->assertSame('marked_pending', $record->superseded_reason);
        Storage::disk('local')->assertExists($record->document_path);
        $this->assertSame('valid', $this->verify($record->uuid)->json('data.status'));

        $this->getJson("/api/clients/{$this->client->uuid}/contract/evidence")
            ->assertOk()
            ->assertJsonPath('data.signatures.0.id', $record->uuid)
            ->assertJsonPath('data.signatures.0.superseded_reason', 'marked_pending');
    }

    public function test_staff_actions_are_recorded_as_distinct_methods(): void
    {
        $this->actingAsAdmin();

        $this->putJson("/api/clients/{$this->client->uuid}/contract/signature", ['signature_status' => 'signed'])->assertOk();
        $manual = ContractSignature::query()->latest('id')->firstOrFail();
        $this->assertSame('manual_attestation', $manual->method->value);

        $content = "%PDF-1.4\n% contrato assinado em papel\n%%EOF";
        $this->post("/api/clients/{$this->client->uuid}/contract/document", [
            'file' => UploadedFile::fake()->createWithContent('contrato.pdf', $content),
        ], ['Accept' => 'application/json'])->assertOk();

        $uploaded = ContractSignature::query()->latest('id')->firstOrFail();

        $this->assertSame('uploaded_document', $uploaded->method->value);
        $this->assertSame(hash('sha256', $content), $uploaded->document_sha256);
        $this->assertNotNull($manual->fresh()->superseded_at);
        $this->assertSame('valid', $this->verify($manual->uuid)->json('data.status'));
        $this->assertSame('valid', $this->verify($uploaded->uuid)->json('data.status'));
    }

    // ---------------------------------------------------------------------
    // Auditoria
    // ---------------------------------------------------------------------

    public function test_events_are_recorded_in_reconstructable_order(): void
    {
        $record = $this->signedRecord();

        $types = ContractEvidenceEvent::query()
            ->where('client_contract_id', $record->client_contract_id)
            ->orderBy('sequence')
            ->pluck('type')
            ->all();

        $this->assertSame([
            'signing_session_started',
            'consent_accepted',
            'signature_received',
            'document_sealed',
            'signature_completed',
            'evidence_protected',
        ], $types);

        $events = ContractEvidenceEvent::query()->orderBy('sequence')->get();

        foreach ($events as $index => $event) {
            $this->assertSame($index === 0 ? null : $events[$index - 1]->hash, $event->previous_hash);
        }

        $report = $this->verify($record->uuid)->json('data');
        $this->assertSame('valid', $this->check($report, 'events_chain'));
        $this->assertSame('valid', $this->check($report, 'events_sequence'));
    }

    public function test_tampering_with_event_trail_is_detected(): void
    {
        $record = $this->signedRecord();

        try {
            DB::table('contract_evidence_events')->where('type', 'consent_accepted')->delete();
            $this->fail('O banco deveria bloquear a exclusão de eventos.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Simula um operador com privilégio para remover a proteção do banco.
        DB::unprepared('DROP TRIGGER contract_evidence_events_no_update');
        DB::table('contract_evidence_events')->where('type', 'signature_received')->update(['ip' => '10.0.0.9']);

        $report = $this->verify($record->uuid)->json('data');

        $this->assertSame('integrity_failure', $report['status']);
        $this->assertSame('integrity_failure', $this->check($report, 'events_chain'));
    }

    public function test_exported_package_verifies_independently_after_storage_loss(): void
    {
        $record = $this->signedRecord();
        $package = $this->exportPackage($record);

        $zip = new ZipArchive;
        $zip->open($package);
        $this->assertSame($record->manifest, $zip->getFromName('manifest.json'));
        $this->assertNotFalse($zip->getFromName('public-key.pem'));
        $this->assertNotFalse($zip->getFromName('artifacts/signed_document.pdf'));
        $zip->close();

        // "Restauração": o sistema perde os arquivos, o pacote continua verificável.
        Storage::fake('local');

        $this->artisan('contract-evidence:verify', ['--package' => $package])->assertExitCode(0);
        $this->assertSame('missing', $this->verify($record->uuid)->json('data.status'));
        $this->assertDatabaseHas('contract_evidence_events', ['type' => 'evidence_exported']);
    }

    public function test_package_signature_can_be_checked_with_plain_public_key(): void
    {
        $record = $this->signedRecord();

        $publicKey = base64_decode($this->key['public_key'], true);

        $this->assertTrue(EvidenceKeyring::verify($record->manifest, $record->manifest_signature, $publicKey));
        $this->assertSame(CanonicalJson::encode(json_decode($record->manifest, true)), $record->manifest);
        $this->assertSame(hash('sha256', $record->manifest), $record->manifest_sha256);
    }

    public function test_no_full_cpf_tokens_or_secrets_in_logs_trail_or_manifest(): void
    {
        $logs = [];
        Log::listen(function (MessageLogged $event) use (&$logs): void {
            $logs[] = $event->message.json_encode($event->context);
        });

        config(['contract_evidence.signing.private_key' => null]);
        $this->signThroughApp();

        $record = ContractSignature::query()->firstOrFail();
        $haystacks = [
            $record->manifest,
            json_encode(ContractEvidenceEvent::query()->get()->toArray()),
            json_encode(DB::table('audit_logs')->get()->all()),
            implode("\n", $logs),
        ];

        foreach ($haystacks as $haystack) {
            $this->assertStringNotContainsString(self::CPF, (string) $haystack);
            $this->assertStringNotContainsString('529.982.247-25', (string) $haystack);
            $this->assertStringNotContainsString($this->key['secret_key'], (string) $haystack);
            $this->assertStringNotContainsStringIgnoringCase('bearer', (string) $haystack);
        }

        $this->assertNotEmpty($logs, 'A falha de proteção deveria ser registrada em log.');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function signThroughApp(): TestResponse
    {
        return $this->complete($this->openSigningSession())->assertOk();
    }

    private function signedRecord(): ContractSignature
    {
        $this->signThroughApp();

        return ContractSignature::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    // ---------------------------------------------------------------------
    // Validação pública (QR Code)
    // ---------------------------------------------------------------------

    public function test_public_validation_shows_minimal_data_without_authentication(): void
    {
        $record = $this->signedRecord();
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/api/public/contract-signatures/{$record->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.protected', true)
            ->assertJsonPath('data.superseded', false)
            ->assertJsonPath('data.contract_name', 'Contrato de rastreamento')
            ->assertJsonPath('data.signer_name', 'Maria da Silva')
            ->assertJsonPath('data.signer_cpf_masked', '***.982.247-**')
            ->assertJsonPath('data.document_sha256', $record->document_sha256)
            ->assertJsonPath('data.company', $this->tenant->name);

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::CPF, $body);
        $this->assertStringNotContainsString('"ip"', $body);
        $this->assertStringNotContainsString('user_agent', $body);
        $this->assertStringNotContainsString('user_id', $body);
    }

    public function test_public_validation_reports_tampering_and_unknown_codes(): void
    {
        $record = $this->signedRecord();
        $this->app['auth']->forgetGuards();

        Storage::disk('local')->put($record->document_path, Storage::disk('local')->get($record->document_path).'%tamper');

        $this->getJson("/api/public/contract-signatures/{$record->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'integrity_failure');

        $this->getJson('/api/public/contract-signatures/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/public/contract-signatures/nao-e-um-codigo')->assertNotFound();
    }

    public function test_verification_url_uses_configured_base(): void
    {
        config(['contract_evidence.verification_url' => null, 'app.frontend_url' => 'https://painel.example.com/']);
        $this->assertSame('https://painel.example.com/validar-contrato/abc', SignedDocumentService::verificationUrl('abc'));

        config(['contract_evidence.verification_url' => 'https://validar.example.com/c/']);
        $this->assertSame('https://validar.example.com/c/abc', SignedDocumentService::verificationUrl('abc'));
    }

    private function openSigningSession(): array
    {
        Sanctum::actingAs($this->clientUser);

        return $this->postJson('/api/contract/portal/signing-sessions', [], ['X-App-Version' => '1.4.0'])
            ->assertCreated()
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $overrides
     */
    private function complete(array $session, array $overrides = [], ?string $image = null): TestResponse
    {
        Sanctum::actingAs($this->clientUser);

        return $this->post("/api/contract/portal/signing-sessions/{$session['id']}/complete", [
            'image' => UploadedFile::fake()->createWithContent('assinatura.png', $image ?? $this->png()),
            'signer_name' => 'Maria da Silva',
            'signer_cpf' => '529.982.247-25',
            'signer_birth_date' => '1990-05-20',
            'consent_accepted' => '1',
            'consent_sha256' => $session['consent']['sha256'],
            'document_sha256' => $session['document']['sha256'],
            ...$overrides,
        ], ['Accept' => 'application/json', 'X-App-Version' => '1.4.0', 'X-App-Platform' => 'android']);
    }

    private function verify(string $signatureUuid): TestResponse
    {
        $this->actingAsAdmin();

        return $this->getJson("/api/clients/{$this->client->uuid}/contract/evidence/{$signatureUuid}/verify")->assertOk();
    }

    private function exportPackage(ContractSignature $record): string
    {
        $this->actingAsAdmin();

        $response = $this->get("/api/clients/{$this->client->uuid}/contract/evidence/{$record->uuid}/export")->assertOk();

        $copy = tempnam(sys_get_temp_dir(), 'pkg-').'.zip';
        copy($response->baseResponse->getFile()->getPathname(), $copy);

        return $copy;
    }

    private function rewriteZip(string $path, string $entry, string $contents): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'pkg-').'.zip';
        copy($path, $copy);

        $zip = new ZipArchive;
        $zip->open($copy);
        $zip->addFromString($entry, $contents);
        $zip->close();

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function check(array $report, string $name): ?string
    {
        foreach ($report['checks'] as $check) {
            if ($check['check'] === $name) {
                return $check['status'];
            }
        }

        return null;
    }

    private function assignContract(Contract $contract): void
    {
        $this->actingAsAdmin();

        $this->putJson("/api/clients/{$this->client->uuid}/contract", ['contract_id' => $contract->uuid])->assertOk();
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs($this->createAdmin($this->tenant));
    }

    private function contractRow(): ClientContract
    {
        return ClientContract::query()->where('client_id', $this->client->getKey())->firstOrFail();
    }

    /**
     * @param  array{key_id: string, secret_key: string, public_key: string}  $active
     * @param  list<array{key_id: string, secret_key: string, public_key: string}>  $trusted
     */
    private function useKey(array $active, array $trusted): void
    {
        config([
            'contract_evidence.signing.key_id' => null,
            'contract_evidence.signing.private_key' => $active['secret_key'],
            'contract_evidence.signing.trusted_public_keys' => implode(',', array_map(
                fn (array $key) => $key['key_id'].':'.$key['public_key'],
                $trusted,
            )),
        ]);
    }

    private function png(int $width = 20, int $height = 10): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 0));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
