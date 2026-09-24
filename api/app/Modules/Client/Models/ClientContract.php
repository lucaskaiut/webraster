<?php

namespace App\Modules\Client\Models;

use App\Modules\Client\Enums\ContractSignatureStatus;
use App\Modules\Contract\Models\Contract;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContract extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'contract_id',
        'valid_until',
        'signature_status',
        'signed_at',
        'signature_path',
        'signer_name',
        'signer_cpf',
        'signer_birth_date',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'signature_status' => ContractSignatureStatus::class,
            'signed_at' => 'datetime',
            'signer_birth_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
