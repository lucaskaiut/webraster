<?php

namespace App\Modules\Auth\DTOs;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class RegisterResult
{
    /**
     * @param  Collection<int, Tenant>  $availableTenants
     * @param  list<array{id: string, name: string, payment_method: string}>  $paymentMethods
     */
    public function __construct(
        public User $user,
        public Tenant $tenant,
        public ?string $token,
        public Collection $availableTenants = new Collection,
        public ?Invoice $invoice = null,
        public bool $requiresPayment = false,
        public bool $isTrial = false,
        public int $trialDays = 0,
        public string $billingStatus = 'none',
        public array $paymentMethods = [],
    ) {}
}
