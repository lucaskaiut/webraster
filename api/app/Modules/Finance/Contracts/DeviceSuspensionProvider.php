<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Equipment\Models\Equipment;

interface DeviceSuspensionProvider
{
    public function suspend(Equipment $equipment): void;

    public function unsuspend(Equipment $equipment): void;
}
