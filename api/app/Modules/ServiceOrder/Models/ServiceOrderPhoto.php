<?php

namespace App\Modules\ServiceOrder\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceOrderPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderPhoto extends Model
{
    /** @use HasFactory<ServiceOrderPhotoFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'service_order_id',
        'path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    protected static function newFactory(): ServiceOrderPhotoFactory
    {
        return ServiceOrderPhotoFactory::new();
    }
}
