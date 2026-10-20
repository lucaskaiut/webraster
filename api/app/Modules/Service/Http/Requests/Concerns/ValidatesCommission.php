<?php

namespace App\Modules\Service\Http\Requests\Concerns;

use App\Modules\Service\Enums\CommissionType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesCommission
{
    /**
     * @return array<string, mixed>
     */
    protected function commissionRules(): array
    {
        return [
            'commission_type' => ['nullable', Rule::enum(CommissionType::class)],
            // Percentual em centésimos de ponto percentual (100 = 1%) ou valor fixo em centavos.
            'commission_value' => ['nullable', 'integer', 'min:0', 'max:99999999', 'required_with:commission_type'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('commission_type') !== CommissionType::PERCENTAGE->value) {
                return;
            }

            $value = $this->input('commission_value');

            if (is_numeric($value) && (int) $value > 10000) {
                $validator->errors()->add('commission_value', 'O percentual de comissão não pode ser superior a 100%.');
            }
        });
    }
}
