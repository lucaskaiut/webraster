<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ScheduleAgendaSignatureRequest extends FormRequest
{
    /** Cobre a janela de busca de horários do painel (90 dias) com folga. */
    public const MAX_DAYS = 120;

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
            'technician_id' => ['required', 'integer'],
            'service_order_id' => ['nullable', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'technician_id.integer' => 'Técnico inválido.',
            'service_order_id.integer' => 'OS inválida.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                $days = CarbonImmutable::parse((string) $this->input('from'))
                    ->diffInDays(CarbonImmutable::parse((string) $this->input('to'))) + 1;

                if ($days > self::MAX_DAYS) {
                    $validator->errors()->add('to', 'O período pode ter no máximo '.self::MAX_DAYS.' dias.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        // Consultas com escopo de tenant: chaves de outro tenant viram inválidas.
        $input = [];

        foreach ([
            'technician_id' => User::class,
            'service_order_id' => ServiceOrder::class,
        ] as $field => $model) {
            if ($this->filled($field)) {
                $input[$field] = $model::query()->where('uuid', (string) $this->input($field))->value('id') ?? 'invalid';
            }
        }

        $this->merge($input);
    }
}
