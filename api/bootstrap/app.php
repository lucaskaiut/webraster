<?php

use App\Modules\ACL\Http\Middleware\EnsurePermission;
use App\Modules\ApiToken\Http\Middleware\AuthenticateApiToken;
use App\Modules\ApiToken\Http\Middleware\MultiAuthenticate;
use App\Modules\Billing\Http\Middleware\EnsureActiveSubscription;
use App\Modules\Shared\Http\ApiError;
use App\Modules\Tenant\Exceptions\TenantAccessForbidden;
use App\Modules\Tenant\Exceptions\TenantCouldNotBeResolved;
use App\Modules\Tenant\Http\Middleware\EnsureChildTenant;
use App\Modules\Tenant\Http\Middleware\ResolveTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/Modules/Webhook/Listeners',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'permission' => EnsurePermission::class,
            'auth.api-token' => AuthenticateApiToken::class,
            'auth.multi' => MultiAuthenticate::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'tenant.child' => EnsureChildTenant::class,
        ]);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveTenant::class,
        );

        $middleware->prependToPriorityList(
            ResolveTenant::class,
            MultiAuthenticate::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ApiError::shouldRender($request),
        );

        $exceptions->render(function (TenantCouldNotBeResolved $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response($e->getMessage(), 404);
            }
        });

        $exceptions->render(function (TenantAccessForbidden $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response($e->getMessage(), 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response('Os dados fornecidos são inválidos.', $e->status, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response('Não autenticado.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                $message = $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                    ? $e->getMessage()
                    : 'Ação não autorizada.';

                return ApiError::response($message, 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response('Recurso não encontrado.', 404);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response('Muitas requisições. Tente novamente em instantes.', 429);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (ApiError::shouldRender($request)) {
                return ApiError::response(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Erro ao processar a requisição.',
                    $e->getStatusCode(),
                );
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (ApiError::shouldRender($request) && ! config('app.debug')) {
                return ApiError::response('Erro interno do servidor.', 500);
            }
        });
    })->create();
