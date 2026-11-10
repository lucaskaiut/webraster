<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\CollectionReminderStage;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Régua de cobrança da empresa (uma por tenant).
 */
class FinanceCollectionSetting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_collection_settings';

    protected $fillable = [
        'is_active',
        'before_due_enabled',
        'before_due_days',
        'before_due_message',
        'due_date_enabled',
        'due_date_message',
        'overdue_enabled',
        'overdue_interval_days',
        'overdue_max_reminders',
        'overdue_message',
        'block_access_enabled',
        'block_access_after_days',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => false,
        'before_due_enabled' => true,
        'before_due_days' => 3,
        'due_date_enabled' => true,
        'overdue_enabled' => true,
        'overdue_interval_days' => 3,
        'block_access_enabled' => false,
        'block_access_after_days' => 5,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'before_due_enabled' => 'boolean',
            'before_due_days' => 'integer',
            'due_date_enabled' => 'boolean',
            'overdue_enabled' => 'boolean',
            'overdue_interval_days' => 'integer',
            'overdue_max_reminders' => 'integer',
            'block_access_enabled' => 'boolean',
            'block_access_after_days' => 'integer',
        ];
    }

    public function stageEnabled(CollectionReminderStage $stage): bool
    {
        return match ($stage) {
            CollectionReminderStage::BEFORE_DUE => $this->before_due_enabled,
            CollectionReminderStage::DUE_DATE => $this->due_date_enabled,
            CollectionReminderStage::OVERDUE => $this->overdue_enabled,
        };
    }

    /** Texto configurado para a etapa, ou o padrão quando vazio. */
    public function messageFor(CollectionReminderStage $stage): string
    {
        $message = match ($stage) {
            CollectionReminderStage::BEFORE_DUE => $this->before_due_message,
            CollectionReminderStage::DUE_DATE => $this->due_date_message,
            CollectionReminderStage::OVERDUE => $this->overdue_message,
        };

        return filled($message) ? (string) $message : $stage->defaultMessage();
    }
}
