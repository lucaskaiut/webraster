<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Support\PaymentDataRules;
use App\Modules\Billing\Support\PaymentGatewayResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateInvoicePaymentRequest extends FormRequest
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
            'payment_gateway' => [
                'required',
                'string',
                Rule::in(app(PaymentGatewayResolver::class)->activeKeys()),
            ],
            ...PaymentDataRules::validationRules(),
        ];
    }
}
