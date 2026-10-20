<?php

namespace App\Modules\ServiceOrder\Models;

use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\Client\Models\Concerns\BelongsToClient;
use App\Modules\Commission\Models\Commission;
use App\Modules\DeviceCommand\Models\DeviceCommandLog;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\ServiceOrder\Enums\ServiceOrderPriority;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Vehicle\Models\Vehicle;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use BelongsToClient;

    use BelongsToTenant;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'number',
        'status',
        'priority',
        'client_id',
        'vehicle_id',
        'equipment_id',
        'technician_id',
        'travel_from_order_id',
        'travel_to_order_id',
        'address',
        'latitude',
        'longitude',
        'scheduled_start_at',
        'scheduled_end_at',
        'estimated_duration_minutes',
        'estimated_travel_minutes',
        'estimated_return_travel_minutes',
        'travel_distance_meters',
        'return_travel_distance_meters',
        'reminder_sent_at',
        'description',
        'notes',
        'installation_location',
        'installation_location_photo_path',
        'execution_notes',
        'loan_term_accepted_at',
        'loan_term_signature_path',
        'loan_term_collected_by',
        'loan_term_text',
        'loan_term_text_hash',
        'cancellation_reason',
        'created_by',
        'completed_by',
        'cancelled_by',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'priority' => ServiceOrderPriority::class,
            'number' => 'integer',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'estimated_duration_minutes' => 'integer',
            'estimated_travel_minutes' => 'integer',
            'estimated_return_travel_minutes' => 'integer',
            'travel_distance_meters' => 'integer',
            'return_travel_distance_meters' => 'integer',
            'reminder_sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'loan_term_accepted_at' => 'datetime',
        ];
    }

    public function getCodeAttribute(): string
    {
        return sprintf('OS-%06d', $this->number);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function travelFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'travel_from_order_id');
    }

    public function travelTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'travel_to_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function loanTermCollectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loan_term_collected_by');
    }

    public function hasAcceptedLoanTerm(): bool
    {
        return $this->loan_term_accepted_at !== null
            && filled($this->loan_term_signature_path);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceOrderHistory::class)->orderByDesc('created_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class)->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServiceOrderPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceOrderAttachment::class)->orderBy('id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(FinanceBilling::class, 'service_order_id')->orderByDesc('id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class)->orderBy('id');
    }

    /**
     * Comandos de teste (bloqueio/desbloqueio) enviados ao equipamento
     * selecionado e vinculados a esta OS para auditoria.
     */
    public function equipmentTests(): HasMany
    {
        return $this->hasMany(DeviceCommandLog::class, 'service_order_id')
            ->orderByDesc('requested_at')
            ->orderByDesc('id');
    }

    public function itemsTotalCents(): int
    {
        return (int) $this->items->sum(fn (ServiceOrderItem $item) => $item->totalCents());
    }

    protected static function newFactory(): ServiceOrderFactory
    {
        return ServiceOrderFactory::new();
    }
}
