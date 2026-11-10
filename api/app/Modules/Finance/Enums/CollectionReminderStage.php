<?php

namespace App\Modules\Finance\Enums;

/**
 * Etapas da régua de cobrança.
 */
enum CollectionReminderStage: string
{
    case BEFORE_DUE = 'before_due';
    case DUE_DATE = 'due_date';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::BEFORE_DUE => 'Antes do vencimento',
            self::DUE_DATE => 'No dia do vencimento',
            self::OVERDUE => 'Em atraso',
        };
    }

    public function subject(): string
    {
        return match ($this) {
            self::BEFORE_DUE => 'Cobrança a vencer',
            self::DUE_DATE => 'Cobrança vence hoje',
            self::OVERDUE => 'Cobrança em atraso',
        };
    }

    /** Ação registrada no histórico da cobrança (finance_billing_events). */
    public function eventAction(): string
    {
        return 'collection_reminder_'.$this->value;
    }

    public function notificationType(): string
    {
        return 'finance_collection_'.$this->value;
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::BEFORE_DUE => 'Olá, {cliente}! Sua cobrança {codigo} de {valor} vence em {vencimento}. Para pagar, acesse: {link}',
            self::DUE_DATE => 'Olá, {cliente}! Sua cobrança {codigo} de {valor} vence hoje ({vencimento}). Para pagar, acesse: {link}',
            self::OVERDUE => 'Olá, {cliente}! Sua cobrança {codigo} de {valor}, vencida em {vencimento}, está em atraso há {dias_atraso} dia(s). Regularize pelo link: {link}',
        };
    }
}
