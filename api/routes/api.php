<?php

use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\ApiToken\Http\Controllers\ApiTokenController;
use App\Modules\Assistant\Http\Controllers\ChatController;
use App\Modules\Assistant\Http\Controllers\ConversationController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Billing\Http\Controllers\InvoiceController;
use App\Modules\Billing\Http\Controllers\PaymentMethodController;
use App\Modules\Billing\Http\Controllers\PlanController;
use App\Modules\Billing\Http\Controllers\SubscriptionController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('select-tenant', [AuthController::class, 'selectTenant']);
    });
});

Route::get('billing/plans/catalog', [PlanController::class, 'catalog']);
Route::get('plans/public', [PlanController::class, 'catalog']);
Route::get('billing/gateways', [SubscriptionController::class, 'gateways']);
Route::get('payment-methods', [PaymentMethodController::class, 'index']);

/*
 * Pagamento e regularização ficam acessíveis mesmo com assinatura PAST_DUE/SUSPENDED.
 */
Route::middleware(['auth.multi:sanctum', 'tenant'])->group(function (): void {
    Route::middleware('tenant.child')->group(function (): void {
        Route::get('billing/subscription', [SubscriptionController::class, 'show'])->middleware('permission:subscription.read');
        Route::get('billing/invoices', [InvoiceController::class, 'index'])->middleware('permission:invoice.read');
        Route::get('billing/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoice.read');
        Route::post('billing/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('permission:invoice.read');
    });

    Route::get('billing/plans', [PlanController::class, 'index'])->middleware('permission:plan.read');
    Route::post('billing/plans', [PlanController::class, 'store'])->middleware('permission:plan.create');
    Route::get('billing/plans/{plan}', [PlanController::class, 'show'])->middleware('permission:plan.read');
    Route::match(['put', 'patch'], 'billing/plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plan.update');
    Route::delete('billing/plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plan.delete');

    Route::middleware('tenant.child')->group(function (): void {
        Route::post('billing/subscription', [SubscriptionController::class, 'store'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/change-plan', [SubscriptionController::class, 'changePlan'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/reactivate', [SubscriptionController::class, 'reactivate'])->middleware('permission:subscription.update');
    });
});

Route::middleware(['auth.multi:sanctum', 'tenant', 'subscription.active'])->group(function (): void {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant', [TenantController::class, 'update'])->middleware('permission:tenant.update');

    Route::get('tenant/children', [TenantController::class, 'index'])->middleware('permission:tenant.read');
    Route::post('tenant/children', [TenantController::class, 'store'])->middleware('permission:tenant.create');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.read');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    Route::get('api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:api-token.read');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->middleware('permission:api-token.create');
    Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-token.delete');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('permission:webhook.read');
    Route::get('webhooks/events', [WebhookController::class, 'events'])->middleware('permission:webhook.read');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('permission:webhook.create');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->middleware('permission:webhook.read');
    Route::match(['put', 'patch'], 'webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('permission:webhook.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('permission:webhook.delete');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->middleware('permission:webhook.read');

    Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

    Route::post('uploads', FileUploadController::class);

    // Assistente de IA
    Route::get('assistant/conversations', [ConversationController::class, 'index'])->middleware('permission:assistant.view');
    Route::post('assistant/conversations', [ConversationController::class, 'store'])->middleware('permission:assistant.view');
    Route::get('assistant/conversations/{conversation}', [ConversationController::class, 'show'])->middleware('permission:assistant.view');
    Route::match(['put', 'patch'], 'assistant/conversations/{conversation}', [ConversationController::class, 'update'])->middleware('permission:assistant.view');
    Route::delete('assistant/conversations/{conversation}', [ConversationController::class, 'destroy'])->middleware('permission:assistant.view');
    Route::post('assistant/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('permission:assistant.view');
});
