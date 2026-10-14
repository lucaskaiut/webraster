<?php

namespace App\Modules\Service\Http\Requests;

use App\Modules\Service\Http\Requests\Concerns\ResolvesLinkedServices;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    use ResolvesLinkedServices;

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
            'name' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            ...$this->linkedServiceRules(),
        ];
    }
}
