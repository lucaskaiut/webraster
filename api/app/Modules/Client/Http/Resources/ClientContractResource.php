<?php

namespace App\Modules\Client\Http\Resources;

use App\Modules\Client\Models\ClientContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientContract
 */
class ClientContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'contract_id' => $this->contract?->uuid,
            'contract_name' => $this->contract?->name,
            'valid_until' => $this->valid_until?->toDateString(),
            'signature_status' => $this->signature_status?->value,
            'signature_status_label' => $this->signature_status?->label(),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signature_path' => $this->signature_path,
            'signature_url' => $this->signatureUrl(),
            'document_path' => $this->document_path,
            'document_url' => $this->documentUrl(),
            'document_name' => $this->document_name,
            'document_hash' => $this->document_hash,
            'signature_hash' => $this->signature_hash,
            'signed_ip' => $this->signed_ip,
            'signed_user_agent' => $this->signed_user_agent,
            'signer_name' => $this->signer_name,
            'signer_cpf' => $this->signer_cpf,
            'signer_birth_date' => $this->signer_birth_date?->toDateString(),
            'body' => $this->renderedBody(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
