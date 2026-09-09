<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\FinanceSubscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly FinanceSubscription $subscription) {}
}
