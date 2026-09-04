<?php

namespace App\Modules\Client\Support\Facades;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\CurrentClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(Client $client)
 * @method static void forget()
 * @method static Client|null client()
 * @method static int|null clientId()
 * @method static bool isResolved()
 *
 * @see CurrentClient
 */
final class ClientContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CurrentClient::class;
    }
}
