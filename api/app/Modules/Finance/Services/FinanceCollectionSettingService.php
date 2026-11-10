<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinanceCollectionSetting;
use App\Modules\Tenant\Support\Facades\TenantContext;

class FinanceCollectionSettingService
{
    /**
     * Régua da empresa atual; sem registro, devolve uma instância com os
     * valores padrão (ainda não salva e desativada).
     */
    public function current(): FinanceCollectionSetting
    {
        return FinanceCollectionSetting::query()->first() ?? new FinanceCollectionSetting;
    }

    public function isActiveForCurrentTenant(): bool
    {
        if (TenantContext::tenant() === null) {
            return false;
        }

        return FinanceCollectionSetting::query()->where('is_active', true)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): FinanceCollectionSetting
    {
        $setting = $this->current();

        foreach (['before_due_message', 'due_date_message', 'overdue_message'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        if (array_key_exists('overdue_max_reminders', $data) && ! $data['overdue_max_reminders']) {
            $data['overdue_max_reminders'] = null;
        }

        $setting->fill($data)->save();

        return $setting->refresh();
    }
}
