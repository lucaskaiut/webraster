<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Enums\CollectionReminderStage;
use App\Modules\Finance\Models\FinanceCollectionSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinanceCollectionSetting
 */
class FinanceCollectionSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_active' => (bool) $this->is_active,
            'before_due_enabled' => (bool) $this->before_due_enabled,
            'before_due_days' => (int) $this->before_due_days,
            'before_due_message' => $this->messageFor(CollectionReminderStage::BEFORE_DUE),
            'due_date_enabled' => (bool) $this->due_date_enabled,
            'due_date_message' => $this->messageFor(CollectionReminderStage::DUE_DATE),
            'overdue_enabled' => (bool) $this->overdue_enabled,
            'overdue_interval_days' => (int) $this->overdue_interval_days,
            'overdue_max_reminders' => $this->overdue_max_reminders,
            'overdue_message' => $this->messageFor(CollectionReminderStage::OVERDUE),
            'block_access_enabled' => (bool) $this->block_access_enabled,
            'block_access_after_days' => (int) $this->block_access_after_days,
            'default_messages' => collect(CollectionReminderStage::cases())
                ->mapWithKeys(fn (CollectionReminderStage $stage) => [$stage->value => $stage->defaultMessage()])
                ->all(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
