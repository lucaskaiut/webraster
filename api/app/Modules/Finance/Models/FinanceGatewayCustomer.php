<?php

namespace App\Modules\Finance\Models;

use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinanceGatewayCustomer extends Model
{
    use BelongsToClient;
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_gateway_customers';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'gateway',
        'external_customer_id',
    ];
}
