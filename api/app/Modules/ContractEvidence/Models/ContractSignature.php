<?php

namespace App\Modules\ContractEvidence\Models;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceStatus;
use App\Modules\ContractEvidence\Enums\SignatureMethod;
use App\Modules\ContractEvidence\Exceptions\ImmutableEvidenceException;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro imutável de uma assinatura. Só a proteção do manifesto
 * (assinatura Ed25519) e a marcação de substituição podem ser gravadas
 * depois — e apenas uma vez. O banco reforça a regra com gatilhos.
 */
class ContractSignature extends Model
{
    use BelongsToTenant;
    use HasUuid;

    /** Colunas graváveis após a criação (as demais são imutáveis). */
    private const MUTABLE = ['evidence_status', 'protection_error', 'updated_at'];

    /** Colunas graváveis uma única vez (de null para valor). */
    private const WRITE_ONCE = ['manifest_signature', 'key_id', 'protected_at', 'superseded_at', 'superseded_reason'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'client_id',
        'client_uuid',
        'client_contract_id',
        'client_contract_uuid',
        'contract_id',
        'session_id',
        'user_id',
        'method',
        'document_version',
        'presented_document_path',
        'presented_document_sha256',
        'document_path',
        'document_sha256',
        'document_size',
        'document_media_type',
        'signature_image_path',
        'signature_image_sha256',
        'signer_name',
        'signer_cpf_masked',
        'signer_cpf_commitment',
        'signer_birth_date_commitment',
        'cpf_matches_client',
        'consent_version',
        'consent_sha256',
        'consent_explicit',
        'consented_at',
        'signed_at',
        'ip',
        'user_agent',
        'app_version',
        'request_id',
        'manifest',
        'manifest_sha256',
        'events_head_hash',
        'evidence_status',
        'manifest_signature',
        'key_id',
        'protected_at',
        'protection_error',
        'superseded_at',
        'superseded_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $signature): void {
            foreach (array_keys($signature->getDirty()) as $column) {
                if (in_array($column, self::MUTABLE, true)) {
                    continue;
                }

                if (in_array($column, self::WRITE_ONCE, true) && $signature->getRawOriginal($column) === null) {
                    continue;
                }

                throw new ImmutableEvidenceException("contract_signatures.{$column} é imutável.");
            }
        });

        static::deleting(function (): void {
            throw new ImmutableEvidenceException('Registros de assinatura não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'method' => SignatureMethod::class,
            'evidence_status' => EvidenceStatus::class,
            'document_version' => 'integer',
            'document_size' => 'integer',
            'cpf_matches_client' => 'boolean',
            'consent_explicit' => 'boolean',
            'consented_at' => 'immutable_datetime',
            'signed_at' => 'immutable_datetime',
            'protected_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    public function clientContract(): BelongsTo
    {
        return $this->belongsTo(ClientContract::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ContractSigningSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isProtected(): bool
    {
        return $this->evidence_status === EvidenceStatus::PROTECTED;
    }
}
