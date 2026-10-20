<?php

namespace App\Modules\Service\Http\Requests;

use App\Modules\Service\Http\Requests\Concerns\ResolvesLinkedServices;
use App\Modules\Service\Http\Requests\Concerns\ValidatesCommission;
use App\Modules\Service\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    use ResolvesLinkedServices;
    use ValidatesCommission;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'amount_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'estimated_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            ...$this->commissionRules(),
            ...$this->linkedServiceRules($service instanceof Service ? $service->getKey() : null),
        ];
    }
}
