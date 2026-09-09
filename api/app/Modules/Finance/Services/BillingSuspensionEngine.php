<?php

namespace App\Modules\Finance\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Contracts\DeviceSuspensionProvider;

class BillingSuspensionEngine
{
    public function __construct(
        private readonly DeviceSuspensionProvider $provider,
    ) {}

    public function suspend(Equipment $equipment): void
    {
        $this->provider->suspend($equipment);
    }

    public function unsuspend(Equipment $equipment): void
    {
        $this->provider->unsuspend($equipment);
    }
}
