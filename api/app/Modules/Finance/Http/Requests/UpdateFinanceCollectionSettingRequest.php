<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceCollectionSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'before_due_enabled' => ['required', 'boolean'],
            'before_due_days' => ['required', 'integer', 'min:1', 'max:30'],
            'before_due_message' => ['nullable', 'string', 'max:2000'],
            'due_date_enabled' => ['required', 'boolean'],
            'due_date_message' => ['nullable', 'string', 'max:2000'],
            'overdue_enabled' => ['required', 'boolean'],
            'overdue_interval_days' => ['required', 'integer', 'min:1', 'max:30'],
            'overdue_max_reminders' => ['nullable', 'integer', 'min:0', 'max:100'],
            'overdue_message' => ['nullable', 'string', 'max:2000'],
            'block_access_enabled' => ['required', 'boolean'],
            'block_access_after_days' => ['required', 'integer', 'min:1', 'max:90'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'before_due_days' => 'dias antes do vencimento',
            'overdue_interval_days' => 'intervalo em atraso',
            'overdue_max_reminders' => 'limite de avisos em atraso',
            'block_access_after_days' => 'dias de atraso para bloqueio',
        ];
    }
}
