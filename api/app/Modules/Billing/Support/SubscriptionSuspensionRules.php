<?php

namespace App\Modules\Billing\Support;

/**
 * Constantes de bloqueio automático — alterar aqui para ajustar a política.
 */
final class SubscriptionSuspensionRules
{
    public const MAX_EXPIRED_INVOICES = 3;

    public const DAYS_WITHOUT_PAYMENT = 30;
}
