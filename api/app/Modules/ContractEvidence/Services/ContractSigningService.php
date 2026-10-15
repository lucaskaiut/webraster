<?php

namespace App\Modules\ContractEvidence\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceEventType;
use App\Modules\ContractEvidence\Enums\EvidenceStatus;
use App\Modules\ContractEvidence\Enums\SignatureMethod;
use App\Modules\ContractEvidence\Enums\SigningSessionStatus;
use App\Modules\ContractEvidence\Exceptions\SigningException;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Models\ContractSigningSession;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceStorage;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Fluxo de assinatura controlado pelo servidor:
 *
 * 1. start(): congela o documento apresentado (snapshot + SHA-256) e o texto
 *    de consentimento numa sessão com validade, vinculada ao usuário/token.
 * 2. complete(): valida sessão, versão e consentimento, reserva a sessão de
 *    forma atômica (open → completing), grava imagem e PDF final no disco
 *    privado e, numa transação, cria o registro imutável, os eventos e o
 *    manifesto. Depois assina o manifesto (Ed25519).
 *
 * O signatário nunca vem do app: cliente, contrato e conta são derivados da
 * sessão autenticada. Nome/CPF/nascimento são registrados como declarados.
 */
class ContractSigningService
{
    public function __construct(
        private readonly EvidenceStorage $storage,
        private readonly EvidenceLedger $ledger,
        private readonly EvidenceManifestService $manifests,
        private readonly SignedDocumentService $documents,
        private readonly AuditLogService $audit,
    ) {}

    public function start(User $user, Client $client, RequestEvidence $context): ContractSigningSession
    {
        $this->assertSigner($user, $client);

        $contract = ClientContract::query()->with('contract')->where('client_id', $client->getKey())->first();

        if ($contract === null || $contract->contract_id === null || blank($contract->body)) {
            throw new SigningException('no_contract', 'Nenhum contrato disponível para assinatura.', 404);
        }

        if ($contract->signature_status === ContractSignatureStatus::SIGNED) {
            throw SigningException::alreadySigned();
        }

        $uuid = (string) Str::uuid();
        $path = $this->storage->sessionPath($contract, $uuid, 'presented.html');
        $sha = $this->storage->write($path, $this->documents->presentedHtml($contract, $uuid));
        $consentText = (string) config('contract_evidence.consent.text');

        try {
            return DB::transaction(function () use ($user, $contract, $context, $uuid, $path, $sha, $consentText) {
                $this->revokeOpenSessions($contract, 'superseded_by_new_session', $context, $user->getKey(), $user->getKey());

                $session = ContractSigningSession::create([
                    'uuid' => $uuid,
                    'tenant_id' => $contract->tenant_id,
                    'client_id' => $contract->client_id,
                    'client_contract_id' => $contract->getKey(),
                    'contract_id' => $contract->contract_id,
                    'user_id' => $user->getKey(),
                    'auth_token_id' => $context->authTokenId,
                    'status' => SigningSessionStatus::OPEN,
                    'document_version' => (int) $contract->document_version,
                    'presented_document_path' => $path,
                    'presented_document_sha256' => $sha,
                    'consent_version' => (string) config('contract_evidence.consent.version'),
                    'consent_text' => $consentText,
                    'consent_sha256' => hash('sha256', $consentText),
                    'expires_at' => CarbonImmutable::now('UTC')->addMinutes((int) config('contract_evidence.session_ttl_minutes', 30)),
                    'ip' => $context->ip,
                    'user_agent' => $context->userAgent,
                    'app_version' => $context->appVersion,
                    'request_id' => $context->requestId,
                ]);

                $this->ledger->record($contract, EvidenceEventType::SESSION_STARTED, [
                    'session_uuid' => $uuid,
                    'document_version' => (int) $contract->document_version,
                    'presented_document_sha256' => $sha,
                    'consent_version' => $session->consent_version,
                    'consent_sha256' => $session->consent_sha256,
                    'expires_at' => CanonicalJson::timestamp($session->expires_at),
                    'auth_method' => $context->authMethod,
                ], $context, $user->getKey(), $session);

                return $session;
            });
        } catch (Throwable $e) {
            $this->storage->discard($path);

            throw $e;
        }
    }

    public function presentedDocument(ContractSigningSession $session): ?string
    {
        return $this->storage->read($session->presented_document_path);
    }

    /**
     * @param  array{image: UploadedFile, signer_name: string, signer_cpf: string, signer_birth_date: string, consent_accepted?: bool, consent_sha256?: ?string, document_sha256?: ?string}  $input
     * @return array{0: ContractSignature, 1: bool} assinatura e se a resposta é idempotente
     */
    public function complete(
        User $user,
        ContractSigningSession $session,
        array $input,
        RequestEvidence $context,
        SignatureMethod $method = SignatureMethod::APP_SIGNATURE,
    ): array {
        $imageBytes = (string) file_get_contents($input['image']->getRealPath());
        $imageSha = hash('sha256', $imageBytes);

        if ((int) $session->user_id !== (int) $user->getKey()) {
            throw new SigningException('session_not_found', 'Sessão de assinatura não encontrada.', 404);
        }

        $contract = ClientContract::query()->with(['contract', 'client'])->find($session->client_contract_id);

        if ($contract === null) {
            throw SigningException::sessionRevoked();
        }

        match ($session->status) {
            SigningSessionStatus::COMPLETED => null,
            SigningSessionStatus::COMPLETING => throw SigningException::inProgress(),
            SigningSessionStatus::EXPIRED => throw SigningException::sessionExpired(),
            SigningSessionStatus::REVOKED => throw SigningException::sessionRevoked(),
            SigningSessionStatus::OPEN => null,
        };

        if ($session->status === SigningSessionStatus::COMPLETED) {
            return [$this->idempotentResult($session, $imageSha, $input['signer_cpf']), true];
        }

        $this->assertSessionUsable($session, $contract, $input, $context, $method, $user);

        $claimed = ContractSigningSession::query()
            ->whereKey($session->getKey())
            ->where('status', SigningSessionStatus::OPEN->value)
            ->update(['status' => SigningSessionStatus::COMPLETING->value, 'updated_at' => now()]);

        if ($claimed === 0) {
            $session->refresh();

            if ($session->status === SigningSessionStatus::COMPLETED) {
                return [$this->idempotentResult($session, $imageSha, $input['signer_cpf']), true];
            }

            throw SigningException::inProgress();
        }

        $signatureUuid = (string) Str::uuid();
        $written = [];

        try {
            $presented = $this->storage->read($session->presented_document_path);

            if ($presented === null || ! hash_equals($session->presented_document_sha256, hash('sha256', $presented))) {
                throw new \RuntimeException('presented_document_integrity');
            }

            $signedAt = CarbonImmutable::now('UTC');
            $cpf = (string) $input['signer_cpf'];
            $cpfMatches = $this->cpfMatchesClient($contract->client?->document, $cpf);

            $imagePath = $this->storage->signaturePath($contract, $signatureUuid, 'signature.'.$this->imageExtension($input['image']));
            $this->storage->write($imagePath, $imageBytes);
            $written[] = $imagePath;

            $pdf = $this->documents->seal(
                $presented,
                $session->uuid,
                $imageBytes,
                $signatureUuid,
                (string) $input['signer_name'],
                $signedAt,
            );

            $pdfPath = $this->storage->signaturePath($contract, $signatureUuid, 'document.pdf');
            $pdfSha = $this->storage->write($pdfPath, $pdf);
            $written[] = $pdfPath;

            $signature = DB::transaction(fn () => $this->persist(
                $user, $session, $contract, $context, $method, $input, $signatureUuid, $signedAt,
                $imagePath, $imageSha, $pdfPath, $pdfSha, strlen($pdf), $cpfMatches,
            ));
        } catch (SigningException $e) {
            $this->storage->discard(...$written);
            $this->revokeSession($session, $contract, $e->reason, $context, $user->getKey(), SigningSessionStatus::COMPLETING);

            throw $e;
        } catch (Throwable $e) {
            $this->storage->discard(...$written);

            ContractSigningSession::query()
                ->whereKey($session->getKey())
                ->where('status', SigningSessionStatus::COMPLETING->value)
                ->update([
                    'status' => SigningSessionStatus::OPEN->value,
                    'failure_count' => DB::raw('failure_count + 1'),
                    'last_error' => Str::limit(class_basename($e).': '.$e->getMessage(), 250, ''),
                    'updated_at' => now(),
                ]);

            $this->safely(fn () => $this->ledger->record($contract, EvidenceEventType::SIGNING_FAILED, [
                'session_uuid' => $session->uuid,
                'reason' => 'persistence_error',
                'error' => class_basename($e),
            ], $context, $user->getKey(), $session, result: 'failure'));

            report($e);

            throw SigningException::persistenceFailed();
        }

        $this->manifests->protect($signature, $context);

        return [$signature->fresh(), false];
    }

    /**
     * Endpoint antigo do app (sem sessão prévia). Mantido para versões já
     * instaladas: registra a assinatura como "legacy", sem consentimento
     * explícito nem confirmação da versão apresentada.
     *
     * @param  array{signer_name: string, signer_cpf: string, signer_birth_date: string}  $signer
     */
    public function signLegacy(User $user, Client $client, int $contractId, UploadedFile $image, array $signer, RequestEvidence $context): ContractSignature
    {
        if (! config('contract_evidence.allow_legacy_signing', true)) {
            throw new SigningException('app_update_required', 'Atualize o aplicativo para assinar o contrato.', 426);
        }

        $contract = ClientContract::query()->where('client_id', $client->getKey())->first();

        if ($contract === null) {
            throw ValidationException::withMessages([
                'contract_id' => ['Nenhum contrato vinculado a este cliente.'],
            ]);
        }

        if ((int) $contract->contract_id !== $contractId) {
            throw ValidationException::withMessages([
                'contract_id' => ['O contrato informado não é o contrato vigente deste cliente.'],
            ]);
        }

        $session = $this->start($user, $client, $context);

        [$signature] = $this->complete($user, $session, [
            'image' => $image,
            ...$signer,
        ], $context, SignatureMethod::LEGACY_APP_SIGNATURE);

        return $signature;
    }

    /**
     * Revoga as sessões abertas do contrato (alteração do documento,
     * assinatura por outro meio, nova sessão do mesmo usuário).
     */
    public function revokeOpenSessions(
        ClientContract $contract,
        string $reason,
        ?RequestEvidence $context = null,
        ?int $actorId = null,
        ?int $onlyUserId = null,
    ): void {
        $sessions = ContractSigningSession::query()
            ->where('client_contract_id', $contract->getKey())
            ->where('status', SigningSessionStatus::OPEN->value)
            ->when($onlyUserId !== null, fn ($query) => $query->where('user_id', $onlyUserId))
            ->get();

        foreach ($sessions as $session) {
            $this->revokeSession($session, $contract, $reason, $context, $actorId);
        }
    }

    public function expire(ContractSigningSession $session, ?RequestEvidence $context = null): void
    {
        $updated = ContractSigningSession::query()
            ->whereKey($session->getKey())
            ->where('status', SigningSessionStatus::OPEN->value)
            ->update(['status' => SigningSessionStatus::EXPIRED->value, 'updated_at' => now()]);

        $contract = ClientContract::withoutGlobalScopes()->withTrashed()->find($session->client_contract_id);

        if ($updated > 0 && $contract !== null) {
            $this->ledger->record($contract, EvidenceEventType::SESSION_EXPIRED, [
                'session_uuid' => $session->uuid,
            ], $context ?? RequestEvidence::system(), session: $session);
        }
    }

    public static function cpfCommitment(string $signatureUuid, string $cpf): string
    {
        return hash('sha256', $signatureUuid.':'.preg_replace('/\D+/', '', $cpf));
    }

    private function persist(
        User $user,
        ContractSigningSession $session,
        ClientContract $contract,
        RequestEvidence $context,
        SignatureMethod $method,
        array $input,
        string $signatureUuid,
        CarbonImmutable $signedAt,
        string $imagePath,
        string $imageSha,
        string $pdfPath,
        string $pdfSha,
        int $pdfSize,
        ?bool $cpfMatches,
    ): ContractSignature {
        $locked = ClientContract::query()->whereKey($contract->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->signature_status === ContractSignatureStatus::SIGNED) {
            throw SigningException::alreadySigned();
        }

        if ((int) $locked->document_version !== (int) $session->document_version
            || (int) $locked->contract_id !== (int) $session->contract_id) {
            throw SigningException::documentChanged();
        }

        $explicit = $method === SignatureMethod::APP_SIGNATURE;
        $cpf = (string) $input['signer_cpf'];

        if ($explicit) {
            $this->ledger->record($locked, EvidenceEventType::CONSENT_ACCEPTED, [
                'session_uuid' => $session->uuid,
                'consent_version' => $session->consent_version,
                'consent_sha256' => $session->consent_sha256,
            ], $context, $user->getKey(), $session);
        }

        $this->ledger->record($locked, EvidenceEventType::SIGNATURE_RECEIVED, [
            'session_uuid' => $session->uuid,
            'signature_image_sha256' => $imageSha,
            'cpf_matches_client' => $cpfMatches,
        ], $context, $user->getKey(), $session);

        $this->ledger->record($locked, EvidenceEventType::DOCUMENT_SEALED, [
            'session_uuid' => $session->uuid,
            'presented_document_sha256' => $session->presented_document_sha256,
            'document_sha256' => $pdfSha,
            'document_size' => $pdfSize,
        ], $context, $user->getKey(), $session);

        $events = ContractEvidenceEvent::query()
            ->where('client_contract_id', $locked->getKey())
            ->where('session_id', $session->getKey())
            ->orderBy('sequence')
            ->get();

        $built = $this->manifests->build([
            'signature_id' => $signatureUuid,
            'method' => $method->value,
            'signed_at' => CanonicalJson::timestamp($signedAt),
            'tenant' => ['id' => (int) $locked->tenant_id],
            'client' => ['id' => (string) $locked->client?->uuid],
            'contract' => [
                'id' => (string) $locked->uuid,
                'template_id' => $locked->contract?->uuid,
                'template_name' => $locked->contract?->name,
                'document_version' => (int) $session->document_version,
            ],
            'session' => [
                'id' => $session->uuid,
                'started_at' => CanonicalJson::timestamp($session->created_at),
                'expires_at' => CanonicalJson::timestamp($session->expires_at),
            ],
            'account' => [
                'user_id' => (string) $user->uuid,
                'authentication' => $context->authMethod,
                'token_id' => $context->authTokenId,
            ],
            'signer' => [
                'declared' => [
                    'name' => $input['signer_name'],
                    'cpf_masked' => $this->maskCpf($cpf),
                    'cpf_commitment' => self::cpfCommitment($signatureUuid, $cpf),
                    'birth_date_commitment' => hash('sha256', $signatureUuid.':'.$input['signer_birth_date']),
                ],
                'commitment_scheme' => 'sha256("<signature_id>:<valor>")',
                'verified' => [
                    'cpf_matches_client_record' => $cpfMatches,
                ],
            ],
            'consent' => [
                'explicit' => $explicit,
                'version' => $explicit ? $session->consent_version : null,
                'text' => $explicit ? $session->consent_text : null,
                'sha256' => $explicit ? $session->consent_sha256 : null,
                'accepted_at' => $explicit ? CanonicalJson::timestamp($signedAt) : null,
            ],
            'artifacts' => [
                'presented_document' => [
                    'media_type' => 'text/html',
                    'sha256' => $session->presented_document_sha256,
                ],
                'signed_document' => [
                    'media_type' => 'application/pdf',
                    'sha256' => $pdfSha,
                    'size' => $pdfSize,
                ],
                'signature_image' => [
                    'media_type' => $input['image']->getMimeType(),
                    'sha256' => $imageSha,
                ],
            ],
            'context' => [
                'request_id' => $context->requestId,
                'observed' => [
                    'ip' => $context->ip,
                    'received_at' => CanonicalJson::timestamp($signedAt),
                ],
                'declared' => [
                    'user_agent' => $context->userAgent,
                    'app_version' => $context->appVersion,
                    'app_platform' => $context->appPlatform,
                ],
            ],
        ], $events);

        $signature = ContractSignature::create([
            'uuid' => $signatureUuid,
            'tenant_id' => $locked->tenant_id,
            'client_id' => $locked->client_id,
            'client_uuid' => (string) $locked->client?->uuid,
            'client_contract_id' => $locked->getKey(),
            'client_contract_uuid' => $locked->uuid,
            'contract_id' => $locked->contract_id,
            'session_id' => $session->getKey(),
            'user_id' => $user->getKey(),
            'method' => $method,
            'document_version' => $session->document_version,
            'presented_document_path' => $session->presented_document_path,
            'presented_document_sha256' => $session->presented_document_sha256,
            'document_path' => $pdfPath,
            'document_sha256' => $pdfSha,
            'document_size' => $pdfSize,
            'document_media_type' => 'application/pdf',
            'signature_image_path' => $imagePath,
            'signature_image_sha256' => $imageSha,
            'signer_name' => $input['signer_name'],
            'signer_cpf_masked' => $this->maskCpf($cpf),
            'signer_cpf_commitment' => self::cpfCommitment($signatureUuid, $cpf),
            'signer_birth_date_commitment' => hash('sha256', $signatureUuid.':'.$input['signer_birth_date']),
            'cpf_matches_client' => $cpfMatches,
            'consent_version' => $explicit ? $session->consent_version : null,
            'consent_sha256' => $explicit ? $session->consent_sha256 : null,
            'consent_explicit' => $explicit,
            'consented_at' => $explicit ? $signedAt : null,
            'signed_at' => $signedAt,
            'ip' => $context->ip,
            'user_agent' => $context->userAgent,
            'app_version' => $context->appVersion,
            'request_id' => $context->requestId,
            'manifest' => $built['manifest'],
            'manifest_sha256' => $built['sha256'],
            'events_head_hash' => $built['head_hash'],
            'evidence_status' => EvidenceStatus::PENDING,
        ]);

        $this->ledger->record($locked, EvidenceEventType::SIGNATURE_COMPLETED, [
            'session_uuid' => $session->uuid,
            'signature_uuid' => $signatureUuid,
            'manifest_sha256' => $built['sha256'],
            'document_sha256' => $pdfSha,
        ], $context, $user->getKey(), $session, $signature);

        $locked->fill([
            'signature_status' => ContractSignatureStatus::SIGNED,
            'signed_at' => $signedAt,
            'signature_path' => $imagePath,
            'document_path' => null,
            'document_name' => null,
            'document_hash' => $pdfSha,
            'signature_hash' => $imageSha,
            'signed_ip' => $context->ip,
            'signed_user_agent' => $context->userAgent,
            'signer_name' => $input['signer_name'],
            'signer_cpf' => $cpf,
            'signer_birth_date' => $input['signer_birth_date'],
        ]);
        $locked->current_signature_id = $signature->getKey();
        $locked->save();

        ContractSigningSession::query()->whereKey($session->getKey())->update([
            'status' => SigningSessionStatus::COMPLETED->value,
            'completed_at' => $signedAt->format('Y-m-d H:i:s.u'),
            'updated_at' => now(),
        ]);

        $this->audit->recordEntity($user, AuditAction::ContractSigned, 'client_contract', (string) $locked->uuid, [
            'signature_status' => ContractSignatureStatus::SIGNED->value,
            'signature_uuid' => $signatureUuid,
            'method' => $method->value,
            'document_hash' => $pdfSha,
            'signature_hash' => $imageSha,
            'manifest_sha256' => $built['sha256'],
        ]);

        return $signature;
    }

    private function assertSessionUsable(
        ContractSigningSession $session,
        ClientContract $contract,
        array $input,
        RequestEvidence $context,
        SignatureMethod $method,
        User $user,
    ): void {
        if ($session->isExpired()) {
            $this->expire($session, $context);

            throw SigningException::sessionExpired();
        }

        if ($session->auth_token_id !== null && $session->auth_token_id !== $context->authTokenId) {
            $this->revokeSession($session, $contract, 'authentication_changed', $context, $user->getKey());

            throw SigningException::sessionRevoked();
        }

        if ($contract->signature_status === ContractSignatureStatus::SIGNED) {
            $this->revokeSession($session, $contract, 'already_signed', $context, $user->getKey());

            throw SigningException::alreadySigned();
        }

        if ((int) $contract->document_version !== (int) $session->document_version
            || (int) $contract->contract_id !== (int) $session->contract_id) {
            $this->revokeSession($session, $contract, 'document_changed', $context, $user->getKey());

            throw SigningException::documentChanged();
        }

        if ($method !== SignatureMethod::APP_SIGNATURE) {
            return;
        }

        if (($input['consent_accepted'] ?? false) !== true
            || ! hash_equals($session->consent_sha256, (string) ($input['consent_sha256'] ?? ''))) {
            $this->reject($contract, $session, 'consent_not_confirmed', $context, $user->getKey());

            throw SigningException::consentRequired();
        }

        if (! hash_equals($session->presented_document_sha256, (string) ($input['document_sha256'] ?? ''))) {
            $this->reject($contract, $session, 'document_hash_mismatch', $context, $user->getKey());

            throw SigningException::documentChanged();
        }
    }

    private function idempotentResult(ContractSigningSession $session, string $imageSha, string $cpf): ContractSignature
    {
        $signature = ContractSignature::query()->where('session_id', $session->getKey())->first();

        if ($signature === null) {
            throw SigningException::inProgress();
        }

        $same = hash_equals((string) $signature->signature_image_sha256, $imageSha)
            && hash_equals((string) $signature->signer_cpf_commitment, self::cpfCommitment($signature->uuid, $cpf));

        if (! $same) {
            throw SigningException::conflictingResubmission();
        }

        return $signature;
    }

    private function revokeSession(
        ContractSigningSession $session,
        ClientContract $contract,
        string $reason,
        ?RequestEvidence $context,
        ?int $actorId,
        SigningSessionStatus $from = SigningSessionStatus::OPEN,
    ): void {
        $updated = ContractSigningSession::query()
            ->whereKey($session->getKey())
            ->where('status', $from->value)
            ->update([
                'status' => SigningSessionStatus::REVOKED->value,
                'revoked_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u'),
                'revoked_reason' => Str::limit($reason, 60, ''),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->ledger->record($contract, EvidenceEventType::SESSION_REVOKED, [
                'session_uuid' => $session->uuid,
                'reason' => $reason,
            ], $context ?? RequestEvidence::system(), $actorId, $session);
        }
    }

    private function reject(ClientContract $contract, ContractSigningSession $session, string $reason, RequestEvidence $context, int $userId): void
    {
        $this->ledger->record($contract, EvidenceEventType::SIGNING_REJECTED, [
            'session_uuid' => $session->uuid,
            'reason' => $reason,
        ], $context, $userId, $session, result: 'failure');
    }

    private function assertSigner(User $user, Client $client): void
    {
        if ((int) $user->client_id !== (int) $client->getKey()) {
            throw new SigningException('not_contract_holder', 'Somente o próprio cliente pode assinar o contrato.', 403);
        }
    }

    private function cpfMatchesClient(?string $document, string $cpf): ?bool
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $document);

        return strlen($digits) === 11 ? hash_equals($digits, $cpf) : null;
    }

    private function maskCpf(string $cpf): string
    {
        $digits = (string) preg_replace('/\D+/', '', $cpf);

        return strlen($digits) === 11
            ? '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**'
            : '***';
    }

    private function imageExtension(UploadedFile $image): string
    {
        return match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
