<?php

namespace App\Modules\ContractEvidence\Http\Controllers;

use App\Modules\ContractEvidence\Enums\EvidenceStatus;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Services\Verification\EvidenceVerifier;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Validação pública (QR Code do contrato assinado). Expõe apenas o mínimo
 * para conferir autenticidade: sem IP, dispositivo, conta, CPF completo ou
 * conteúdo do contrato.
 */
class PublicSignatureVerificationController extends ApiController
{
    public function __invoke(string $code, EvidenceVerifier $verifier): JsonResponse
    {
        abort_unless(Str::isUuid($code), 404);

        $signature = ContractSignature::withoutGlobalScopes()->where('uuid', Str::lower($code))->firstOrFail();
        $manifest = json_decode((string) $signature->manifest, true) ?: [];

        return $this->success([
            'code' => $signature->uuid,
            'status' => $verifier->verifyStored($signature)->status(),
            'protected' => $signature->evidence_status === EvidenceStatus::PROTECTED,
            'superseded' => $signature->superseded_at !== null,
            'superseded_at' => $signature->superseded_at?->toIso8601ZuluString(),
            'method' => $signature->method->value,
            'method_label' => $signature->method->label(),
            'signed_at' => $signature->signed_at?->toIso8601ZuluString(),
            'company' => Tenant::query()->whereKey($signature->tenant_id)->value('name'),
            'contract_name' => $manifest['contract']['template_name'] ?? null,
            'document_version' => $signature->document_version,
            'signer_name' => $signature->signer_name,
            'signer_cpf_masked' => $signature->signer_cpf_masked,
            'consent_explicit' => $signature->consent_explicit,
            'document_sha256' => $signature->document_sha256,
            'manifest_sha256' => $signature->manifest_sha256,
            'key_id' => $signature->key_id,
        ]);
    }
}
