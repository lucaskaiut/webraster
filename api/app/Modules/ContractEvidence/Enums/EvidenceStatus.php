<?php

namespace App\Modules\ContractEvidence\Enums;

enum EvidenceStatus: string
{
    // Manifesto gravado, aguardando assinatura Ed25519 (chave ausente/falha).
    case PENDING = 'pending';
    // Manifesto assinado com a chave do backend.
    case PROTECTED = 'protected';
}
