<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('finance_webhook_logs');
        Schema::dropIfExists('finance_asaas_customers');
        Schema::dropIfExists('tenant_asaas_configs');
        Schema::dropIfExists('finance_receivable_events');
        Schema::dropIfExists('finance_receivables');
        Schema::dropIfExists('finance_billing_events');
        Schema::dropIfExists('finance_billings');
        Schema::dropIfExists('finance_subscriptions');
        Schema::dropIfExists('finance_contracts');
        Schema::dropIfExists('finance_plans');

        if (Schema::hasColumn('clients', 'plan_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plan_id');
            });
        }

        Schema::create('finance_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('periodicity', 20)->default('monthly');
            $table->unsignedInteger('device_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('is_active')
                ->constrained('finance_plans')
                ->nullOnDelete();
        });

        Schema::create('finance_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('finance_plans')->nullOnDelete();
            $table->string('plan_name')->nullable();
            $table->unsignedBigInteger('plan_price_cents')->nullable();
            $table->string('plan_periodicity', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->boolean('block_on_overdue')->default(true);
            $table->unsignedInteger('block_after_days')->default(5);
            $table->date('last_billed_at')->nullable();
            $table->date('next_billing_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'next_billing_at']);
            $table->index(['tenant_id', 'plan_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX finance_subscriptions_tenant_client_unique
                 ON finance_subscriptions (tenant_id, client_id)
                 WHERE deleted_at IS NULL'
            );
        }

        Schema::create('finance_billings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('subscription_id')->constrained('finance_subscriptions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('payment_method', 20)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('fine_cents')->default(0);
            $table->unsignedBigInteger('interest_cents')->default(0);
            $table->unsignedBigInteger('paid_amount_cents')->nullable();
            $table->date('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('invoice_url')->nullable();
            $table->string('bank_slip_url')->nullable();
            $table->text('pix_qr_code')->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->string('description')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'subscription_id']);
            $table->index(['tenant_id', 'due_at']);
            $table->index(['tenant_id', 'gateway_payment_id']);
        });

        Schema::create('finance_billing_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('billing_id')->constrained('finance_billings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['billing_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('tenant_asaas_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('environment', 20)->default('sandbox');
            $table->text('api_key');
            $table->text('webhook_token');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id']);
        });

        Schema::create('finance_asaas_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('asaas_customer_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'asaas_customer_id']);
        });

        Schema::create('finance_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_id')->nullable();
            $table->string('event')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('status', 20)->default('received');
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id']);
            $table->index(['tenant_id', 'payment_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_webhook_logs');
        Schema::dropIfExists('finance_asaas_customers');
        Schema::dropIfExists('tenant_asaas_configs');
        Schema::dropIfExists('finance_billing_events');
        Schema::dropIfExists('finance_billings');
        Schema::dropIfExists('finance_subscriptions');

        if (Schema::hasColumn('clients', 'plan_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plan_id');
            });
        }

        Schema::dropIfExists('finance_plans');
    }
};
