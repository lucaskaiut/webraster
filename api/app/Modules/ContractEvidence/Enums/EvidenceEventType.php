<?php

namespace App\Modules\ContractEvidence\Enums;

enum EvidenceEventType: string
{
    case SESSION_STARTED = 'signing_session_started';
    case SESSION_EXPIRED = 'signing_session_expired';
    case SESSION_REVOKED = 'signing_session_revoked';
    case SESSION_RECOVERED = 'signing_session_recovered';
    case CONSENT_ACCEPTED = 'consent_accepted';
    case SIGNATURE_RECEIVED = 'signature_received';
    case DOCUMENT_SEALED = 'document_sealed';
    case SIGNATURE_COMPLETED = 'signature_completed';
    case SIGNING_REJECTED = 'signing_rejected';
    case SIGNING_FAILED = 'signing_failed';
    case EVIDENCE_PROTECTED = 'evidence_protected';
    case EVIDENCE_PROTECTION_FAILED = 'evidence_protection_failed';
    case SIGNATURE_SUPERSEDED = 'signature_superseded';
    case DOCUMENT_UPLOADED = 'document_uploaded';
    case MANUAL_ATTESTATION = 'manual_attestation';
    case CONTRACT_WAIVED = 'contract_waived';
    case DOCUMENT_VERSION_CHANGED = 'document_version_changed';
    case EVIDENCE_EXPORTED = 'evidence_exported';
}
