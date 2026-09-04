<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Support\PaymentGatewayResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
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
            'plan_id' => ['required', 'string', 'uuid'],
            'payment_gateway' => [
                'nullable',
                'string',
                Rule::in(app(PaymentGatewayResolver::class)->activeKeys()),
            ],
        ];
    }
}
