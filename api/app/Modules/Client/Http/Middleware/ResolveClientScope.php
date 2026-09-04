<?php

namespace App\Modules\Client\Http\Middleware;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\CurrentClient;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ativa o isolamento por cliente quando o usuário autenticado
 * é um usuário de portal (users.client_id preenchido).
 */
class ResolveClientScope
{
    public function __construct(
        private readonly CurrentClient $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->forget();

        $user = $request->user();

        if ($user instanceof User && $user->client_id !== null) {
            $client = Client::query()
                ->withoutClientScope()
                ->find($user->client_id);

            if ($client !== null) {
                $this->context->set($client);
            }
        }

        return $next($request);
    }
}
