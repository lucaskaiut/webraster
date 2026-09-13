<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\FinanceBilling;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly FinanceBilling $billing) {}
}
