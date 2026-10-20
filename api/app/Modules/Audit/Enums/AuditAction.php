<?php

namespace App\Modules\Audit\Enums;

enum AuditAction: string
{
    case MasterLogin = 'master_login';
    case TenantSelected = 'tenant_selected';
    case TenantSwitched = 'tenant_switched';
    case AlertAcknowledged = 'alert_acknowledged';
    case AlertResolved = 'alert_resolved';
    case ContractSigned = 'contract_signed';
    case ContractSignatureUpdated = 'contract_signature_updated';
    case ContractDocumentUploaded = 'contract_document_uploaded';
    case EquipmentStockMoved = 'equipment_stock_moved';
}
