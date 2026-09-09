<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\FinanceReceivable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceDueSoon
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly FinanceReceivable $receivable) {}
}
