<?php

namespace App\Providers;

use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Policies\RolePolicy;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Assistant\Policies\ConversationPolicy;
use App\Modules\ApiToken\Policies\ApiTokenPolicy;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Billing\Policies\PlanPolicy;
use App\Modules\Billing\Policies\SubscriptionPolicy;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Policies\ClientPolicy;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Policies\DriverPolicy;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Equipment\Policies\EquipmentPolicy;
use App\Modules\Geofence\Models\Geofence;
use App\Modules\Geofence\Models\GeofenceEvent;
use App\Modules\Geofence\Policies\GeofenceEventPolicy;
use App\Modules\Geofence\Policies\GeofencePolicy;
use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Models\AlertConfig;
use App\Modules\Alert\Policies\AlertConfigPolicy;
use App\Modules\Alert\Policies\AlertPolicy;
use App\Modules\Poi\Models\Poi;
use App\Modules\Poi\Policies\PoiPolicy;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinancePlan;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Models\TenantAsaasConfig;
use App\Modules\Finance\Policies\FinanceContractPolicy;
use App\Modules\Finance\Policies\FinanceDashboardPolicy;
use App\Modules\Finance\Policies\FinancePlanPolicy;
use App\Modules\Finance\Policies\FinanceReceivablePolicy;
use App\Modules\Finance\Policies\FinanceReportPolicy;
use App\Modules\Finance\Policies\FinanceSubscriptionPolicy;
use App\Modules\Finance\Policies\TenantAsaasConfigPolicy;
use App\Modules\Finance\Support\FinanceDashboard;
use App\Modules\Finance\Support\FinanceReport;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Policies\ServiceOrderPolicy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Policies\TenantPolicy;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Policies\VehiclePolicy;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Policies\WebhookPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureRouteBindings();
    }

    private function configureRouteBindings(): void
    {
        // /api/devices/{device} resolve o equipamento local (fonte do traccar_device_id).
        Route::bind('device', function (string $value) {
            return Equipment::query()->where('uuid', $value)->firstOrFail();
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });


    }

    private function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(ApiToken::class, ApiTokenPolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Equipment::class, EquipmentPolicy::class);
        Gate::policy(Geofence::class, GeofencePolicy::class);
        Gate::policy(GeofenceEvent::class, GeofenceEventPolicy::class);
        Gate::policy(Poi::class, PoiPolicy::class);
        Gate::policy(Alert::class, AlertPolicy::class);
        Gate::policy(AlertConfig::class, AlertConfigPolicy::class);
        Gate::policy(ServiceOrder::class, ServiceOrderPolicy::class);
        Gate::policy(FinancePlan::class, FinancePlanPolicy::class);
        Gate::policy(FinanceContract::class, FinanceContractPolicy::class);
        Gate::policy(FinanceSubscription::class, FinanceSubscriptionPolicy::class);
        Gate::policy(FinanceReceivable::class, FinanceReceivablePolicy::class);
        Gate::policy(TenantAsaasConfig::class, TenantAsaasConfigPolicy::class);
        Gate::policy(FinanceDashboard::class, FinanceDashboardPolicy::class);
        Gate::policy(FinanceReport::class, FinanceReportPolicy::class);
    }
}
