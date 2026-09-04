<?php

namespace App\Modules\Client\Models\Scopes;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\Facades\ClientContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClientScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! ClientContext::isResolved()) {
            return;
        }

        $column = method_exists($model, 'clientScopeColumn')
            ? $model->clientScopeColumn()
            : 'client_id';

        $builder->where(
            $model->qualifyColumn($column),
            ClientContext::clientId(),
        );
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutClientScope', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forClient', function (Builder $builder, Client|int $client): Builder {
            $model = $builder->getModel();
            $column = method_exists($model, 'clientScopeColumn')
                ? $model->clientScopeColumn()
                : 'client_id';

            return $builder
                ->withoutGlobalScope($this)
                ->where(
                    $model->qualifyColumn($column),
                    $client instanceof Client ? $client->getKey() : $client,
                );
        });
    }
}
