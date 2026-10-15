<?php

namespace App\Modules\ContractEvidence\Enums;

enum SignatureMethod: string
{
    // Assinatura no aplicativo com sessão, snapshot e consentimento explícito.
    case APP_SIGNATURE = 'app_signature';
    // Endpoint antigo do app: sem confirmação de versão e sem consentimento explícito.
    case LEGACY_APP_SIGNATURE = 'legacy_app_signature';
    // Documento assinado fora da plataforma e importado pela equipe.
    case UPLOADED_DOCUMENT = 'uploaded_document';
    // Equipe declarou o contrato como assinado, sem artefato de assinatura.
    case MANUAL_ATTESTATION = 'manual_attestation';

    public function label(): string
    {
        return match ($this) {
            self::APP_SIGNATURE => 'Assinatura eletrônica no aplicativo',
            self::LEGACY_APP_SIGNATURE => 'Assinatura no aplicativo (versão antiga, sem consentimento explícito)',
            self::UPLOADED_DOCUMENT => 'Documento assinado importado pela equipe',
            self::MANUAL_ATTESTATION => 'Marcado como assinado pela equipe',
        };
    }
}
