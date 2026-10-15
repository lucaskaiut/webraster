<?php

namespace App\Modules\ContractEvidence\Models;

use App\Modules\ContractEvidence\Exceptions\ImmutableEvidenceException;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento da trilha de evidências (append-only, encadeado por hash por
 * vínculo de contrato). Nunca contém CPF completo, tokens ou credenciais.
 */
class ContractEvidenceEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'client_contract_id',
        'client_contract_uuid',
        'sequence',
        'type',
        'result',
        'occurred_at',
        'session_id',
        'signature_id',
        'user_id',
        'request_id',
        'ip',
        'user_agent',
        'app_version',
        'data',
        'previous_hash',
        'hash',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ImmutableEvidenceException('Eventos de evidência são somente inclusão.');
        });

        static::deleting(function (): void {
            throw new ImmutableEvidenceException('Eventos de evidência são somente inclusão.');
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'data' => 'array',
        ];
    }
}
