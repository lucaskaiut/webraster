<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipelines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'active']);
        });

        Schema::create('crm_pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('color', 32)->nullable();
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['pipeline_id', 'position']);
        });

        Schema::create('chat_messaging_connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('name');
            $table->string('base_url');
            $table->text('credentials');
            $table->string('instance_name')->nullable();
            $table->string('connection_status', 32)->default('disconnected');
            $table->json('capabilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
        });

        Schema::create('chat_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'external_id']);
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('chat_contacts')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('crm_pipeline_stages')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('source', 64)->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('ai_enabled')->default(true);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'pipeline_id', 'stage_id']);
            $table->index(['tenant_id', 'contact_id', 'status']);
        });

        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('contact_id')->constrained('chat_contacts')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('chat_messaging_connections')->cascadeOnDelete();
            $table->string('external_conversation_id')->nullable();
            $table->string('chat_type', 16)->default('individual');
            $table->string('status', 32)->default('open');
            $table->string('ai_mode', 32)->default('ai_active');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_message_at')->nullable();
            $table->timestamp('last_outbound_message_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'external_conversation_id']);
            $table->index(['tenant_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('direction', 16);
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->string('message_type', 32);
            $table->text('text')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->string('media_name')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'external_id']);
            $table->index(['conversation_id', 'sent_at']);
        });

        Schema::create('chat_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('chat_messaging_connections')->cascadeOnDelete();
            $table->string('dedupe_key')->unique();
            $table->string('event_type', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_ai_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->longText('system_prompt')->nullable();
            $table->string('model')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_ai_stage_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('stage_id')->unique()->constrained('crm_pipeline_stages')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->text('objective')->nullable();
            $table->longText('instructions')->nullable();
            $table->text('success_criteria')->nullable();
            $table->json('allowed_actions')->nullable();
            $table->json('restricted_actions')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_ai_tool_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->string('tool', 64);
            $table->json('arguments')->nullable();
            $table->json('result')->nullable();
            $table->string('status', 32);
            $table->text('error')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_ai_tool_executions');
        Schema::dropIfExists('crm_ai_stage_configurations');
        Schema::dropIfExists('crm_ai_configurations');
        Schema::dropIfExists('chat_webhook_events');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('chat_contacts');
        Schema::dropIfExists('chat_messaging_connections');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
