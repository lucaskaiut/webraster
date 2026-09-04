<?php

namespace App\Modules\Client\Models\Concerns;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\Scopes\ClientScope;
use App\Modules\Client\Support\Facades\ClientContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolamento automático por cliente (portal), análogo ao BelongsToTenant.
 *
 * Use em entidades que pertencem a um cliente (motoristas, veículos, etc.).
 * No próprio model Client, sobrescreva clientScopeColumn() para retornar "id".
 *
 * @method static \Illuminate\Database\Eloquent\Builder withoutClientScope()
 * @method static \Illuminate\Database\Eloquent\Builder forClient(\App\Modules\Client\Models\Client|int $client)
 */
trait BelongsToClient
{
    protected static function bootBelongsToClient(): void
    {
        static::addGlobalScope(new ClientScope);

        static::creating(function (Model $model): void {
            $column = method_exists($model, 'clientScopeColumn')
                ? $model->clientScopeColumn()
                : 'client_id';

            // Não sobrescreve a PK do próprio Client.
            if ($column === $model->getKeyName()) {
                return;
            }

            if (blank($model->getAttribute($column)) && ClientContext::isResolved()) {
                $model->setAttribute($column, ClientContext::clientId());
            }
        });
    }

    public function clientScopeColumn(): string
    {
        return 'client_id';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
