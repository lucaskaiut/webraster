<?php

namespace App\Modules\Vehicle\Models;

use App\Modules\Client\Models\Client;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autoriza um cliente a visualizar um veículo cadastrado em outro cliente.
 * A propriedade do veículo (vehicles.client_id) não é alterada.
 */
class VehicleShare extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'client_id',
        'created_by',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Cliente autorizado a visualizar o veículo. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
