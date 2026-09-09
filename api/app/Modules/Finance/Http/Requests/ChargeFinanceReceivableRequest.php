<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChargeFinanceReceivableRequest extends FormRequest
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
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'credit_card' => ['sometimes', 'array'],
            'credit_card.holderName' => ['required_with:credit_card', 'string'],
            'credit_card.number' => ['required_with:credit_card', 'string'],
            'credit_card.expiryMonth' => ['required_with:credit_card', 'string'],
            'credit_card.expiryYear' => ['required_with:credit_card', 'string'],
            'credit_card.ccv' => ['required_with:credit_card', 'string'],
            'creditCardHolderInfo' => ['sometimes', 'array'],
        ];
    }
}
