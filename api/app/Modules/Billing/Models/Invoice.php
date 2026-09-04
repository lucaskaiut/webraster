<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'gateway',
        'amount',
        'status',
        'payment_method',
        'external_id',
        'pix_code',
        'pix_qrcode',
        'due_date',
        'paid_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function awaitsPaymentMethod(): bool
    {
        return $this->isOpen() && blank($this->external_id);
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}
