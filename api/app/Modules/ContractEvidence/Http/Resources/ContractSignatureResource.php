<?php

namespace App\Modules\ContractEvidence\Http\Resources;

use App\Modules\ContractEvidence\Models\ContractSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractSignature
 */
class ContractSignatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'signed_at' => $this->signed_at?->toIso8601ZuluString('microsecond'),
            'document_version' => $this->document_version,
            'presented_document_sha256' => $this->presented_document_sha256,
            'document_sha256' => $this->document_sha256,
            'signature_image_sha256' => $this->signature_image_sha256,
            'manifest_sha256' => $this->manifest_sha256,
            'evidence_status' => $this->evidence_status->value,
            'key_id' => $this->key_id,
            'protected_at' => $this->protected_at?->toIso8601ZuluString('microsecond'),
            'superseded_at' => $this->superseded_at?->toIso8601ZuluString('microsecond'),
            'superseded_reason' => $this->superseded_reason,
            'consent_explicit' => $this->consent_explicit,
            'consent_version' => $this->consent_version,
            'signer_name' => $this->signer_name,
            'signer_cpf_masked' => $this->signer_cpf_masked,
            'cpf_matches_client' => $this->cpf_matches_client,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'app_version' => $this->app_version,
        ];
    }
}
