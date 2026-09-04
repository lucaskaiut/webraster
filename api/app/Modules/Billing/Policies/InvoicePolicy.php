<?php

namespace App\Modules\Billing\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::INVOICE_READ);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $invoice->tenant_id)
            && $user->hasPermission(Permission::INVOICE_READ);
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
