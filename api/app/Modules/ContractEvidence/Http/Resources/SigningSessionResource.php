<?php

namespace App\Modules\ContractEvidence\Http\Resources;

use App\Modules\ContractEvidence\Models\ContractSigningSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractSigningSession
 */
class SigningSessionResource extends JsonResource
{
    public function __construct(ContractSigningSession $resource, private readonly ?string $html = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at->toIso8601ZuluString(),
            'contract' => [
                'id' => $this->clientContract?->uuid,
                'name' => $this->clientContract?->contract?->name,
            ],
            'document' => [
                'version' => $this->document_version,
                'sha256' => $this->presented_document_sha256,
                'html' => $this->html,
            ],
            'consent' => [
                'version' => $this->consent_version,
                'text' => $this->consent_text,
                'sha256' => $this->consent_sha256,
            ],
        ];
    }
}
