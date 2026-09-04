<?php

namespace App\Modules\Billing\Http\Middleware;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Shared\Http\ApiError;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Empresas filhas precisam de assinatura ACTIVE/TRIALING.
     * O tenant raiz (umbrella / sem parent) pode operar o painel sem assinatura própria.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantContext::isResolved()) {
            return ApiError::response('Tenant não resolvido.', 404);
        }

        /** @var Tenant $tenant */
        $tenant = TenantContext::tenant();

        if ($tenant->parent_id === null) {
            return $next($request);
        }

        $subscription = Subscription::query()
            ->withoutTenancy()
            ->where('tenant_id', $tenant->getKey())
            ->first();

        if ($subscription === null) {
            return ApiError::response(
                'Assinatura não encontrada. Escolha um plano para continuar.',
                402,
            );
        }

        if (! $subscription->allowsAccess()) {
            $message = match ($subscription->status) {
                SubscriptionStatus::PAST_DUE => 'Pagamento pendente. Regularize sua assinatura.',
                SubscriptionStatus::SUSPENDED => 'Assinatura suspensa por inadimplência.',
                SubscriptionStatus::CANCELLED => 'Assinatura cancelada.',
                default => 'Assinatura inativa.',
            };

            return ApiError::response($message, 402);
        }

        return $next($request);
    }
}
