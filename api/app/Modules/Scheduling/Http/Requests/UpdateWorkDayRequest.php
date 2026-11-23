<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\Services\WorkDayAdjustmentService;
use App\Modules\Scheduling\Support\TimeGrid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWorkDayRequest extends FormRequest
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
        $earliest = WorkDayAdjustmentService::EARLIEST;
        $latest = WorkDayAdjustmentService::LATEST;

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['present', 'nullable', 'date_format:H:i', "after_or_equal:{$earliest}"],
            'ends_at' => ['present', 'nullable', 'date_format:H:i', "before_or_equal:{$latest}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.after_or_equal' => 'A jornada não pode começar antes de '.WorkDayAdjustmentService::EARLIEST.'.',
            'ends_at.before_or_equal' => 'A jornada não pode terminar depois de '.WorkDayAdjustmentService::LATEST.'.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $grid = TimeGrid::size();

                foreach (['starts_at', 'ends_at'] as $field) {
                    $value = $this->input($field);

                    if (! is_string($value) || ! preg_match('/^\d{2}:\d{2}$/', $value)) {
                        continue;
                    }

                    if ((int) substr($value, 3, 2) % $grid !== 0) {
                        $validator->errors()->add($field, "Use horários em intervalos de {$grid} minutos.");
                    }
                }
            },
        ];
    }
}
