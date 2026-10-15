<?php

namespace App\Modules\Client\Models;

use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Client\Services\ClientContractPdfService;
use App\Modules\Client\Services\ContractBodyRenderer;
use App\Modules\Contract\Models\Contract;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Support\EvidenceStorage;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContract extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $attributes = [
        'document_version' => 1,
    ];

    protected $fillable = [
        'client_id',
        'contract_id',
        'valid_until',
        'signature_status',
        'signed_at',
        'signature_path',
        'document_path',
        'document_name',
        'document_hash',
        'signature_hash',
        'signed_ip',
        'signed_user_agent',
        'signer_name',
        'signer_cpf',
        'signer_birth_date',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'signature_status' => ContractSignatureStatus::class,
            'signed_at' => 'datetime',
            'signer_birth_date' => 'date',
            'document_version' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function currentSignature(): BelongsTo
    {
        return $this->belongsTo(ContractSignature::class, 'current_signature_id');
    }

    /**
     * protected/pending: assinatura com cadeia de evidências (manifesto
     * assinado ou aguardando proteção); legacy: assinado antes da cadeia de
     * evidências, sem registros retroativos; none: não assinado.
     */
    public function evidenceLevel(): string
    {
        if ($this->signature_status !== ContractSignatureStatus::SIGNED) {
            return 'none';
        }

        return $this->currentSignature?->evidence_status->value ?? 'legacy';
    }

    /**
     * Imagens no armazenamento privado de evidências são entregues inline
     * (data URI) para não existir URL pública da assinatura.
     */
    public function signatureUrl(): ?string
    {
        if ($this->signature_path === null) {
            return null;
        }

        $evidence = app(EvidenceStorage::class);

        if ($evidence->isEvidencePath($this->signature_path)) {
            $bytes = $evidence->read($this->signature_path);

            return $bytes !== null ? ClientContractPdfService::dataUri($bytes) : null;
        }

        return asset("storage/{$this->signature_path}");
    }

    public function documentUrl(): ?string
    {
        if ($this->document_path === null || app(EvidenceStorage::class)->isEvidencePath($this->document_path)) {
            return null;
        }

        return asset("storage/{$this->document_path}");
    }

    public function tenantLogoUrl(): ?string
    {
        return $this->tenant?->logoUrl();
    }

    public function tenantSignatureUrl(): ?string
    {
        return $this->tenant?->signatureUrl();
    }

    public function renderedBody(): string
    {
        $renderer = app(ContractBodyRenderer::class);

        $body = $renderer->renderLogo((string) $this->body, $this->tenantLogoUrl());
        $body = $renderer->renderCompanySignature($body, $this->tenantSignatureUrl());

        return $renderer->renderSignature($body, $this->signatureUrl());
    }
}
