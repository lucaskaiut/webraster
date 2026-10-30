<?php

namespace Database\Seeders\Demo;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Enums\RecurrenceUnit;
use App\Modules\Billing\Enums\SubscriptionEventType;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionEvent;
use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant umbrella (Nuvemax), tenant filho com os dados da demonstração,
 * perfis de acesso, equipe interna e assinatura do filho junto ao umbrella.
 */
class DemoTenantSeeder extends Seeder
{
    public function run(DemoContext $ctx, RoleService $roles, AlertConfigService $alerts): void
    {
        $password = Hash::make(DemoContext::PASSWORD);

        $ctx->umbrella = Tenant::query()->create([
            'parent_id' => null,
            'name' => 'Nuvemax',
            'identifier' => 'nuvemax',
            'document' => '11222333000181',
            'email' => 'contato@nuvemax.localhost',
            'phone' => '41999999999',
        ]);

        $umbrellaRoles = $roles->createDefaultRolesFor($ctx->umbrella);

        $master = new User([
            'name' => 'Administrador Nuvemax',
            'email' => 'admin@nuvemax.localhost',
            'phone' => '41999999999',
            'document' => '52998224725',
            'password' => $password,
            'type' => UserType::STAFF->value,
        ]);
        $master->forceFill(['tenant_id' => $ctx->umbrella->getKey(), 'is_master' => true])->save();
        $master->assignRole($umbrellaRoles[DefaultRole::ADMINISTRATOR->value]);

        $plans = $this->seedUmbrellaPlans($ctx->umbrella);

        $ctx->tenant = Tenant::query()->create([
            'parent_id' => $ctx->umbrella->getKey(),
            'name' => 'Rastro Sul Rastreamento',
            'identifier' => 'rastrosul',
            'document' => '45723174000110',
            'email' => 'contato@rastrosul.localhost',
            'phone' => '4133224455',
            'late_fine_percent' => 2,
            'late_interest_percent' => 1,
            'app_name' => 'Rastro Sul',
            'app_primary_color' => '#0F766E',
            'app_secondary_color' => '#F59E0B',
            'require_equipment_loan_acceptance' => true,
            'equipment_loan_term' => 'Declaro ter recebido em comodato o equipamento de rastreamento instalado '
                .'no veículo descrito nesta ordem de serviço, comprometendo-me a zelar por sua guarda e conservação '
                .'e a devolvê-lo à Rastro Sul Rastreamento ao término do contrato, sob pena de cobrança do valor '
                .'do equipamento conforme tabela vigente.',
            'created_at' => $ctx->now->subMonths(14),
        ]);

        $ctx->roles = $roles->createDefaultRolesFor($ctx->tenant);
        $ctx->roles['Financeiro'] = $this->financeRole($ctx->tenant);

        $team = [
            ['Carlos Eduardo Ramos', 'admin@rastrosul.localhost', DefaultRole::ADMINISTRATOR->value],
            ['Mariana Costa', 'mariana.costa@rastrosul.localhost', DefaultRole::OPERATOR->value],
            ['Rafael Mendes', 'rafael.mendes@rastrosul.localhost', DefaultRole::OPERATOR->value],
            ['Patrícia Lima', 'patricia.lima@rastrosul.localhost', 'Financeiro'],
        ];

        foreach ($team as [$name, $email, $role]) {
            $user = new User([
                'name' => $name,
                'email' => $email,
                'phone' => $ctx->mobile(),
                'document' => Document::fakeCpf(),
                'password' => $password,
                'type' => UserType::STAFF->value,
            ]);
            $user->forceFill([
                'tenant_id' => $ctx->tenant->getKey(),
                'created_at' => $ctx->now->subMonths(13),
            ])->save();
            $user->assignRole($ctx->roles[$role]);

            $ctx->staff[] = $user;
        }

        $ctx->admin = $ctx->staff[0];

        $alerts->ensureDefaults($ctx->tenant);

        $this->seedChildSubscription($ctx, $plans['Pro']);
    }

    /**
     * @return array<string, Plan>
     */
    private function seedUmbrellaPlans(Tenant $umbrella): array
    {
        $plans = [
            ['Starter', 'Ideal para começar com o essencial.', '49.90', 7],
            ['Pro', 'Para times em crescimento com mais volume.', '99.90', 7],
            ['Business', 'Recursos avançados para operação em escala.', '199.90', 0],
        ];

        $created = [];

        foreach ($plans as [$name, $description, $price, $trial]) {
            $created[$name] = Plan::query()->create([
                'tenant_id' => $umbrella->getKey(),
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'recurrence_value' => 1,
                'recurrence_unit' => RecurrenceUnit::MONTHS,
                'free_trial_days' => $trial,
                'active' => true,
            ]);
        }

        return $created;
    }

    private function financeRole(Tenant $tenant): Role
    {
        $role = new Role(['name' => 'Financeiro', 'description' => 'Cobranças, assinaturas e relatórios financeiros.']);
        $role->tenant_id = $tenant->getKey();
        $role->save();

        $role->syncPermissions([
            Permission::CLIENT_READ,
            Permission::CLIENT_UPDATE,
            Permission::VEHICLE_READ,
            Permission::CONTRACT_READ,
            Permission::SERVICE_READ,
            Permission::SERVICE_ORDER_READ,
            Permission::SERVICE_ORDER_VIEW_ALL,
            Permission::COMMISSION_READ,
            Permission::COMMISSION_VIEW_OTHERS,
            Permission::REPORT_VIEW,
            Permission::REPORT_EXPORT,
            Permission::FINANCE_PLAN_CREATE,
            Permission::FINANCE_PLAN_READ,
            Permission::FINANCE_PLAN_UPDATE,
            Permission::FINANCE_SUBSCRIPTION_CREATE,
            Permission::FINANCE_SUBSCRIPTION_READ,
            Permission::FINANCE_SUBSCRIPTION_UPDATE,
            Permission::FINANCE_BILLING_CREATE,
            Permission::FINANCE_BILLING_READ,
            Permission::FINANCE_BILLING_UPDATE,
            Permission::FINANCE_BILLING_CHARGE,
            Permission::FINANCE_DASHBOARD_READ,
            Permission::FINANCE_REPORT_READ,
            Permission::NOTIFICATION_READ,
            Permission::NOTIFICATION_SEND,
        ]);

        return $role;
    }

    /**
     * Assinatura do tenant filho no plano Pro do umbrella, com as faturas
     * mensais já quitadas.
     */
    private function seedChildSubscription(DemoContext $ctx, Plan $plan): void
    {
        $startedAt = $ctx->now->subMonths(12)->startOfMonth()->addDays(4)->setTime(10, 0);
        $months = (int) $startedAt->diffInMonths($ctx->now);

        $subscription = Subscription::query()->create([
            'tenant_id' => $ctx->tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'payment_gateway' => null,
            'recurring' => false,
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => $startedAt,
            'last_billed_at' => $startedAt->addMonths($months),
            'next_billing_at' => $startedAt->addMonths($months + 1),
            'created_at' => $startedAt,
        ]);

        SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->getKey(),
            'event' => SubscriptionEventType::SUBSCRIPTION_CREATED,
            'payload' => ['plan' => $plan->name],
            'created_at' => $startedAt,
        ]);

        for ($month = 0; $month <= $months; $month++) {
            $due = $startedAt->addMonths($month);
            $paid = $due->addHours($ctx->faker->numberBetween(1, 30));

            $invoice = Invoice::query()->create([
                'tenant_id' => $ctx->tenant->getKey(),
                'subscription_id' => $subscription->getKey(),
                'gateway' => null,
                'amount' => $plan->price,
                'status' => InvoiceStatus::PAID,
                'payment_method' => PaymentMethod::PIX,
                'due_date' => $due,
                'paid_at' => $paid,
                'expires_at' => $due->addDays(3),
                'created_at' => $due,
            ]);

            SubscriptionEvent::query()->create([
                'subscription_id' => $subscription->getKey(),
                'event' => SubscriptionEventType::INVOICE_GENERATED,
                'payload' => ['invoice_id' => $invoice->uuid],
                'created_at' => $due,
            ]);

            SubscriptionEvent::query()->create([
                'subscription_id' => $subscription->getKey(),
                'event' => SubscriptionEventType::PAYMENT_CONFIRMED,
                'payload' => ['invoice_id' => $invoice->uuid],
                'created_at' => $paid,
            ]);
        }
    }
}
