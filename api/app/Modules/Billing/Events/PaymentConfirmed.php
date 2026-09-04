<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Invoice $invoice,
    ) {}
}
