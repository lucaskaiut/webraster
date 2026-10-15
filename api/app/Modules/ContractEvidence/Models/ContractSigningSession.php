<?php

namespace App\Modules\ContractEvidence\Models;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\SigningSessionStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractSigningSession extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'client_id',
        'client_contract_id',
        'contract_id',
        'user_id',
        'auth_token_id',
        'status',
        'document_version',
        'presented_document_path',
        'presented_document_sha256',
        'consent_version',
        'consent_text',
        'consent_sha256',
        'expires_at',
        'completed_at',
        'revoked_at',
        'revoked_reason',
        'failure_count',
        'last_error',
        'ip',
        'user_agent',
        'app_version',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SigningSessionStatus::class,
            'document_version' => 'integer',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'failure_count' => 'integer',
        ];
    }

    public function clientContract(): BelongsTo
    {
        return $this->belongsTo(ClientContract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signature(): HasOne
    {
        return $this->hasOne(ContractSignature::class, 'session_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
