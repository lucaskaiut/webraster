<?php

namespace App\Modules\Client\Models;

use App\Modules\Service\Models\Service;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientOrderItem extends Model
{
    protected $fillable = [
        'client_order_id',
        'service_id',
        'service_name',
        'unit_amount_cents',
        'quantity',
        'line_total_cents',
    ];

    protected function casts(): array
    {
        return [
            'unit_amount_cents' => 'integer',
            'quantity' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ClientOrder::class, 'client_order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'client_order_item_vehicle')
            ->withPivot([])
            ->orderBy('plate');
    }
}
