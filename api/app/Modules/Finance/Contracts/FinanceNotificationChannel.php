<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Support\FinanceNotificationMessage;

interface FinanceNotificationChannel
{
    public function send(FinanceNotificationMessage $message): void;
}
