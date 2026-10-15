<?php

namespace App\Modules\ContractEvidence\Services;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceEventType;
use App\Modules\ContractEvidence\Enums\EvidenceStatus;
use App\Modules\ContractEvidence\Enums\SignatureMethod;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceStorage;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Evidências das ações da equipe sobre o contrato (importação de documento,
 * marcação manual, substituição, dispensa). Nada é apagado: a assinatura
 * anterior é marcada como substituída e continua verificável.
 */
class ContractEvidenceRecorder
{
    public function __construct(
        private readonly EvidenceStorage $storage,
        private readonly EvidenceLedger $ledger,
        private readonly EvidenceManifestService $manifests,
        private readonly SignedDocumentService $documents,
        private readonly ContractSigningService $signing,
    ) {}

    /**
     * Deve ser chamado dentro da transação que altera o vínculo. Retorna a
     * assinatura criada; o manifesto é assinado após o commit.
     *
     * @param  array{bytes: string, media_type: string, name: ?string, extension: string}|null  $document
     */
    public function recordStaffSignature(
        ClientContract $contract,
        SignatureMethod $method,
        ?User $actor,
        RequestEvidence $context,
        ?array $document = null,
    ): ContractSignature {
        $signatureUuid = (string) Str::uuid();
        $signedAt = CarbonImmutable::now('UTC');
        $written = [];

        try {
            $presentedPath = null;
            $presentedSha = null;

            if (filled($contract->body)) {
                $presentedPath = $this->storage->signaturePath($contract, $signatureUuid, 'presented.html');
                $presentedSha = $this->storage->write($presentedPath, $this->documents->presentedHtml($contract, $signatureUuid));
                $written[] = $presentedPath;
            }

            $documentPath = null;
            $documentSha = null;

            if ($document !== null) {
                $documentPath = $this->storage->signaturePath($contract, $signatureUuid, 'document.'.$document['extension']);
                $documentSha = $this->storage->write($documentPath, $document['bytes']);
                $written[] = $documentPath;
            }

            $this->supersedeCurrent($contract, 'replaced', $context, $actor);
            $this->signing->revokeOpenSessions($contract, 'signed_by_staff', $context, $actor?->getKey());

            $this->ledger->record(
                $contract,
                $method === SignatureMethod::UPLOADED_DOCUMENT ? EvidenceEventType::DOCUMENT_UPLOADED : EvidenceEventType::MANUAL_ATTESTATION,
                array_filter([
                    'signature_uuid' => $signatureUuid,
                    'document_sha256' => $documentSha,
                    'document_media_type' => $document['media_type'] ?? null,
                    'document_size' => $document !== null ? strlen($document['bytes']) : null,
                    'presented_document_sha256' => $presentedSha,
                ], fn ($value) => $value !== null),
                $context,
                $actor?->getKey(),
            );

            $events = ContractEvidenceEvent::query()
                ->where('client_contract_id', $contract->getKey())
                ->orderByDesc('sequence')
                ->limit(1)
                ->get();

            $contract->loadMissing(['client', 'contract']);

            $built = $this->manifests->build([
                'signature_id' => $signatureUuid,
                'method' => $method->value,
                'signed_at' => CanonicalJson::timestamp($signedAt),
                'tenant' => ['id' => (int) $contract->tenant_id],
                'client' => ['id' => (string) $contract->client?->uuid],
                'contract' => [
                    'id' => (string) $contract->uuid,
                    'template_id' => $contract->contract?->uuid,
                    'template_name' => $contract->contract?->name,
                    'document_version' => (int) $contract->document_version,
                ],
                'session' => null,
                'account' => [
                    'user_id' => $actor?->uuid,
                    'authentication' => $context->authMethod,
                    'token_id' => $context->authTokenId,
                    'role' => 'staff',
                ],
                'signer' => null,
                'consent' => null,
                'artifacts' => [
                    'presented_document' => $presentedSha !== null ? ['media_type' => 'text/html', 'sha256' => $presentedSha] : null,
                    'signed_document' => $documentSha !== null ? [
                        'media_type' => $document['media_type'],
                        'sha256' => $documentSha,
                        'size' => strlen($document['bytes']),
                    ] : null,
                    'signature_image' => null,
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
                'tenant_id' => $contract->tenant_id,
                'client_id' => $contract->client_id,
                'client_uuid' => (string) $contract->client?->uuid,
                'client_contract_id' => $contract->getKey(),
                'client_contract_uuid' => $contract->uuid,
                'contract_id' => $contract->contract_id,
                'user_id' => $actor?->getKey(),
                'method' => $method,
                'document_version' => (int) $contract->document_version,
                'presented_document_path' => $presentedPath,
                'presented_document_sha256' => $presentedSha,
                'document_path' => $documentPath,
                'document_sha256' => $documentSha,
                'document_size' => $document !== null ? strlen($document['bytes']) : null,
                'document_media_type' => $document['media_type'] ?? null,
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

            $this->ledger->record($contract, EvidenceEventType::SIGNATURE_COMPLETED, [
                'signature_uuid' => $signatureUuid,
                'manifest_sha256' => $built['sha256'],
                'method' => $method->value,
            ], $context, $actor?->getKey(), signature: $signature);
        } catch (Throwable $e) {
            $this->storage->discard(...$written);

            throw $e;
        }

        DB::afterCommit(fn () => $this->manifests->protect($signature, $context));

        return $signature;
    }

    /**
     * Marca a assinatura vigente como substituída (sem apagá-la) e revoga
     * sessões abertas. Chamado quando o vínculo volta a pendente ou muda de
     * contrato.
     */
    public function supersedeCurrent(ClientContract $contract, string $reason, RequestEvidence $context, ?User $actor): void
    {
        if ($contract->current_signature_id !== null) {
            $signature = ContractSignature::query()->find($contract->current_signature_id);

            if ($signature !== null && $signature->superseded_at === null) {
                $signature->forceFill([
                    'superseded_at' => CarbonImmutable::now('UTC'),
                    'superseded_reason' => $reason,
                ])->save();

                $this->ledger->record($contract, EvidenceEventType::SIGNATURE_SUPERSEDED, [
                    'signature_uuid' => $signature->uuid,
                    'reason' => $reason,
                ], $context, $actor?->getKey(), signature: $signature);
            }

            $contract->current_signature_id = null;
        }

        $this->signing->revokeOpenSessions($contract, $reason, $context, $actor?->getKey());
    }

    public function documentVersionChanged(ClientContract $contract, int $previous, RequestEvidence $context, ?User $actor): void
    {
        $this->ledger->record($contract, EvidenceEventType::DOCUMENT_VERSION_CHANGED, [
            'previous_version' => $previous,
            'document_version' => (int) $contract->document_version,
            'body_sha256' => hash('sha256', (string) $contract->body),
        ], $context, $actor?->getKey());
    }

    public function contractWaived(ClientContract $contract, RequestEvidence $context, ?User $actor): void
    {
        $this->ledger->record($contract, EvidenceEventType::CONTRACT_WAIVED, [], $context, $actor?->getKey());
    }
}
