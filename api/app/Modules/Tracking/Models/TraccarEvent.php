<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tracking\Enums\TraccarEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inbox de tudo que o Traccar envia ao webhook. O payload bruto é preservado
 * para reprocessamento e auditoria; o processamento roda em fila.
 */
class TraccarEvent extends Model
{
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'traccar_device_id',
        'dedupe_key',
        'status',
        'attempts',
        'payload',
        'last_error',
        'received_at',
        'dispatched_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TraccarEventStatus::class,
            'attempts' => 'integer',
            'payload' => 'array',
            'received_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function markProcessing(): void
    {
        $this->forceFill(['status' => TraccarEventStatus::PROCESSING])->save();
    }

    public function markDispatched(): void
    {
        $this->forceFill(['dispatched_at' => now()])->save();
    }

    public function markProcessed(): void
    {
        $this->forceFill([
            'status' => TraccarEventStatus::PROCESSED,
            'processed_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markIgnored(string $reason): void
    {
        $this->forceFill([
            'status' => TraccarEventStatus::IGNORED,
            'processed_at' => now(),
            'last_error' => $reason,
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => TraccarEventStatus::FAILED,
            'processed_at' => now(),
            'last_error' => mb_substr($reason, 0, 1000),
        ])->save();
    }

    public function recordError(string $message): void
    {
        $this->forceFill([
            'last_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
