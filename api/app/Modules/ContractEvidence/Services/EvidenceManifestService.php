<?php

namespace App\Modules\ContractEvidence\Services;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceEventType;
use App\Modules\ContractEvidence\Enums\EvidenceStatus;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manifesto de evidências (formato nuvemax.contract-evidence v1).
 *
 * O manifesto é serializado de forma canônica uma única vez e os bytes são
 * gravados; a assinatura Ed25519 cobre exatamente esses bytes.
 */
class EvidenceManifestService
{
    public const FORMAT = 'nuvemax.contract-evidence';

    public const VERSION = 1;

    public function __construct(
        private readonly EvidenceKeyring $keyring,
        private readonly EvidenceLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $parts
     * @param  Collection<int, ContractEvidenceEvent>  $events  eventos já gravados que o manifesto cobre
     * @return array{manifest: string, sha256: string, head_hash: string}
     */
    public function build(array $parts, Collection $events): array
    {
        $head = $events->last();

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'canonicalization' => CanonicalJson::NAME,
            'hash_algorithm' => 'sha256',
            ...$parts,
            'events' => [
                'head_sequence' => $head?->sequence,
                'head_hash' => $head?->hash,
                'items' => $events->map(fn (ContractEvidenceEvent $event) => [
                    'sequence' => (int) $event->sequence,
                    'type' => $event->type,
                    'occurred_at' => CanonicalJson::timestamp($event->occurred_at),
                    'hash' => $event->hash,
                ])->values()->all(),
            ],
        ];

        $bytes = CanonicalJson::encode($manifest);

        return [
            'manifest' => $bytes,
            'sha256' => hash('sha256', $bytes),
            'head_hash' => (string) $head?->hash,
        ];
    }

    /**
     * Assina o manifesto com a chave ativa. Sem chave (ou em falha) a
     * evidência fica "pending" e o reconciliador tenta novamente; o manifesto
     * e os hashes já gravados não mudam.
     */
    public function protect(ContractSignature $signature, ?RequestEvidence $context = null): bool
    {
        if ($signature->isProtected()) {
            return true;
        }

        $contract = ClientContract::withoutGlobalScopes()->withTrashed()->find($signature->client_contract_id);

        try {
            if (! hash_equals($signature->manifest_sha256, hash('sha256', $signature->manifest))) {
                throw new \RuntimeException('manifest_hash_mismatch');
            }

            $signed = $this->keyring->sign($signature->manifest);

            $signature->forceFill([
                'evidence_status' => EvidenceStatus::PROTECTED,
                'manifest_signature' => $signed['signature'],
                'key_id' => $signed['key_id'],
                'protected_at' => CarbonImmutable::now('UTC'),
                'protection_error' => null,
            ])->save();

            if ($contract !== null) {
                $this->ledger->record($contract, EvidenceEventType::EVIDENCE_PROTECTED, [
                    'signature_uuid' => $signature->uuid,
                    'manifest_sha256' => $signature->manifest_sha256,
                    'key_id' => $signed['key_id'],
                    'algorithm' => EvidenceKeyring::ALGORITHM,
                ], $context ?? RequestEvidence::system(), signature: $signature);
            }

            return true;
        } catch (Throwable $e) {
            $reason = $this->keyring->hasSigningKey() ? 'signing_error' : 'signing_key_missing';

            Log::warning('contract_evidence: manifesto não protegido', [
                'signature' => $signature->uuid,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            $signature->forceFill(['protection_error' => $reason])->save();

            if ($contract !== null) {
                $this->ledger->record($contract, EvidenceEventType::EVIDENCE_PROTECTION_FAILED, [
                    'signature_uuid' => $signature->uuid,
                    'reason' => $reason,
                ], $context ?? RequestEvidence::system(), signature: $signature, result: 'failure');
            }

            return false;
        }
    }
}
