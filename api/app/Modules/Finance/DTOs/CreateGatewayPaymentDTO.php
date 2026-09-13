<?php

namespace App\Modules\Finance\DTOs;

use App\Modules\Finance\Enums\PaymentMethod;
use Carbon\CarbonInterface;

final readonly class CreateGatewayPaymentDTO
{
    public function __construct(
        public string $customerExternalId,
        public string $amount,
        public PaymentMethod $paymentMethod,
        public CarbonInterface $dueDate,
        public string $externalReference,
        public ?string $description = null,
        public ?CreditCardDTO $creditCard = null,
    ) {}
}
