<?php

namespace App\Modules\ServiceOrder\Models;

use App\Modules\DeviceCommand\Enums\DeviceCommandStatus;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teste realizado no equipamento selecionado de uma OS (bloqueio, desbloqueio
 * ou leitura da ignição). O comando enviado ao rastreador, quando existir,
 * continua auditado em device_command_logs.
 */
class ServiceOrderEquipmentTest extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'service_order_id',
        'equipment_id',
        'user_id',
        'device_command_log_id',
        'action',
        'command_type',
        'status',
        'result',
        'requested_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'status' => DeviceCommandStatus::class,
            'requested_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commandLog(): BelongsTo
    {
        return $this->belongsTo(DeviceCommandLog::class, 'device_command_log_id');
    }
}
