<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinanceAsaasCustomer extends Model
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_asaas_customers';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'asaas_customer_id',
    ];
}
