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
use App\Modules\Client\Http\Controllers\ClientController;
use App\Modules\Client\Http\Controllers\ClientUserController;
use App\Modules\Driver\Http\Controllers\DriverController;
use App\Modules\Alert\Http\Controllers\AlertConfigController;
use App\Modules\Alert\Http\Controllers\AlertController;
use App\Modules\Alert\Http\Controllers\NotificationController;
use App\Modules\Equipment\Http\Controllers\EquipmentController;
use App\Modules\Geofence\Http\Controllers\GeofenceController;
use App\Modules\Geofence\Http\Controllers\GeofenceEventController;
use App\Modules\Poi\Http\Controllers\PoiController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\Tracking\Http\Controllers\TrackingController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Vehicle\Http\Controllers\VehicleController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant', 'client.scope'])->group(function (): void {
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
Route::middleware(['auth.multi:sanctum', 'tenant', 'client.scope'])->group(function (): void {
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

Route::middleware(['auth.multi:sanctum', 'tenant', 'client.scope'])->group(function (): void {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant', [TenantController::class, 'update'])->middleware('permission:tenant.update');

    Route::get('tenant/children', [TenantController::class, 'index'])->middleware('permission:tenant.read');
    Route::post('tenant/children', [TenantController::class, 'store'])->middleware('permission:tenant.create');
    Route::get('tenant/children/{child}', [TenantController::class, 'showChild'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant/children/{child}', [TenantController::class, 'updateChild'])->middleware('permission:tenant.update');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::get('clients', [ClientController::class, 'index'])->middleware('permission:client.read');
    Route::post('clients', [ClientController::class, 'store'])->middleware('permission:client.create');
    Route::get('clients/{client}', [ClientController::class, 'show'])->middleware('permission:client.read');
    Route::match(['put', 'patch'], 'clients/{client}', [ClientController::class, 'update'])->middleware('permission:client.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->middleware('permission:client.delete');

    Route::get('clients/{client}/users', [ClientUserController::class, 'index'])->middleware('permission:client.read');
    Route::post('clients/{client}/users', [ClientUserController::class, 'store'])->middleware('permission:client.update');
    Route::match(['put', 'patch'], 'clients/{client}/users/{user}', [ClientUserController::class, 'update'])->middleware('permission:client.update');
    Route::delete('clients/{client}/users/{user}', [ClientUserController::class, 'destroy'])->middleware('permission:client.update');

    Route::get('drivers', [DriverController::class, 'index'])->middleware('permission:driver.read');
    Route::post('drivers', [DriverController::class, 'store'])->middleware('permission:driver.create');
    Route::get('drivers/{driver}', [DriverController::class, 'show'])->middleware('permission:driver.read');
    Route::match(['put', 'patch'], 'drivers/{driver}', [DriverController::class, 'update'])->middleware('permission:driver.update');
    Route::delete('drivers/{driver}', [DriverController::class, 'destroy'])->middleware('permission:driver.delete');

    Route::get('vehicles', [VehicleController::class, 'index'])->middleware('permission:vehicle.read');
    Route::post('vehicles', [VehicleController::class, 'store'])->middleware('permission:vehicle.create');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->middleware('permission:vehicle.read');
    Route::match(['put', 'patch'], 'vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicle.update');
    Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicle.delete');
    Route::get('vehicles/{vehicle}/equipment-history', [VehicleController::class, 'assignmentHistory'])->middleware('permission:vehicle.read');
    Route::post('vehicles/{vehicle}/equipment/install', [VehicleController::class, 'installEquipment'])->middleware('permission:vehicle.update');
    Route::post('vehicles/{vehicle}/equipment/remove', [VehicleController::class, 'removeEquipment'])->middleware('permission:vehicle.update');
    Route::post('vehicles/{vehicle}/equipment/swap', [VehicleController::class, 'swapEquipment'])->middleware('permission:vehicle.update');

    Route::get('equipments', [EquipmentController::class, 'index'])->middleware('permission:equipment.read');
    Route::post('equipments', [EquipmentController::class, 'store'])->middleware('permission:equipment.create');
    Route::get('equipments/{equipment}', [EquipmentController::class, 'show'])->middleware('permission:equipment.read');
    Route::match(['put', 'patch'], 'equipments/{equipment}', [EquipmentController::class, 'update'])->middleware('permission:equipment.update');
    Route::delete('equipments/{equipment}', [EquipmentController::class, 'destroy'])->middleware('permission:equipment.delete');
    Route::get('equipments/{equipment}/assignment-history', [EquipmentController::class, 'assignmentHistory'])->middleware('permission:equipment.read');

    Route::get('tracking/status', [TrackingController::class, 'status'])->middleware('permission:tracking.read');
    Route::get('tracking/live', [TrackingController::class, 'live'])->middleware('permission:tracking.read');
    Route::get('tracking/vehicles/{vehicle}/history', [TrackingController::class, 'history'])->middleware('permission:tracking.read');

    Route::get('geofences', [GeofenceController::class, 'index'])->middleware('permission:geofence.read');
    Route::get('geofences/map', [GeofenceController::class, 'map'])->middleware('permission:geofence.read');
    Route::post('geofences', [GeofenceController::class, 'store'])->middleware('permission:geofence.create');
    Route::get('geofences/{geofence}', [GeofenceController::class, 'show'])->middleware('permission:geofence.read');
    Route::match(['put', 'patch'], 'geofences/{geofence}', [GeofenceController::class, 'update'])->middleware('permission:geofence.update');
    Route::delete('geofences/{geofence}', [GeofenceController::class, 'destroy'])->middleware('permission:geofence.delete');

    Route::get('geofence-events', [GeofenceEventController::class, 'index'])->middleware('permission:geofence.read');

    Route::get('pois', [PoiController::class, 'index'])->middleware('permission:poi.read');
    Route::get('pois/map', [PoiController::class, 'map'])->middleware('permission:poi.read');
    Route::get('poi-categories', [PoiController::class, 'categories'])->middleware('permission:poi.read');
    Route::post('pois', [PoiController::class, 'store'])->middleware('permission:poi.create');
    Route::get('pois/{poi}', [PoiController::class, 'show'])->middleware('permission:poi.read');
    Route::match(['put', 'patch'], 'pois/{poi}', [PoiController::class, 'update'])->middleware('permission:poi.update');
    Route::delete('pois/{poi}', [PoiController::class, 'destroy'])->middleware('permission:poi.delete');

    Route::get('alerts', [AlertController::class, 'index'])->middleware('permission:alert.read');
    Route::get('alerts/map', [AlertController::class, 'map'])->middleware('permission:alert.read');
    Route::get('alerts/dashboard', [AlertController::class, 'dashboard'])->middleware('permission:alert.read');
    Route::get('alerts/{alert}', [AlertController::class, 'show'])->middleware('permission:alert.read');
    Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->middleware('permission:alert.manage');
    Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->middleware('permission:alert.manage');

    Route::get('alert-configs', [AlertConfigController::class, 'index'])->middleware('permission:alert-config.read');
    Route::post('alert-configs', [AlertConfigController::class, 'store'])->middleware('permission:alert-config.update');
    Route::match(['put', 'patch'], 'alert-configs/{alertConfig}', [AlertConfigController::class, 'update'])->middleware('permission:alert-config.update');
    Route::delete('alert-configs/{alertConfig}', [AlertConfigController::class, 'destroy'])->middleware('permission:alert-config.update');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

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
