<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Support\PaymentGatewayResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentGatewayConfigRequest extends FormRequest
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
        $keys = app(PaymentGatewayResolver::class)->activeKeys();

        return [
            'gateway' => ['required', 'string', Rule::in($keys)],
            'is_active' => ['sometimes', 'boolean'],
            'credentials' => ['required', 'array'],
        ];
    }
}
