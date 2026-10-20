<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot dos checklists por OS, respostas e fotografias.
 *
 * Perguntas e opções são copiadas do modelo no momento em que a instância é
 * criada; alterações posteriores no modelo não modificam atendimentos passados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('checklist_id')->nullable()->constrained('checklists')->nullOnDelete();
            $table->string('type', 20);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['service_order_id', 'checklist_id'],
                'service_order_checklists_order_checklist_unique',
            );
            $table->index(['tenant_id', 'service_order_id', 'type'], 'service_order_checklists_order_type_idx');
            $table->index(['tenant_id', 'service_order_id', 'status'], 'service_order_checklists_status_idx');
        });

        Schema::create('service_order_checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_checklist_id')
                ->constrained('service_order_checklists', 'id', 'so_checklist_questions_checklist_fk')
                ->cascadeOnDelete();
            $table->foreignId('checklist_question_id')
                ->nullable()
                ->constrained('checklist_questions', 'id', 'so_checklist_questions_question_fk')
                ->nullOnDelete();
            $table->text('prompt');
            $table->string('answer_type', 30);
            $table->string('photo_rule', 20);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_order_checklist_id', 'position'],
                'so_checklist_questions_order_idx',
            );
        });

        Schema::create('service_order_checklist_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_checklist_question_id')
                ->constrained('service_order_checklist_questions', 'id', 'so_checklist_options_question_fk')
                ->cascadeOnDelete();
            $table->foreignId('checklist_question_option_id')
                ->nullable()
                ->constrained('checklist_question_options', 'id', 'so_checklist_options_template_option_fk')
                ->nullOnDelete();
            $table->string('label', 255);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_order_checklist_question_id', 'position'],
                'so_checklist_options_order_idx',
            );
        });

        Schema::create('service_order_checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_checklist_question_id')
                ->constrained('service_order_checklist_questions', 'id', 'so_checklist_answers_question_fk')
                ->cascadeOnDelete();
            $table->text('text_answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['service_order_checklist_question_id'],
                'so_checklist_answers_question_unique',
            );
        });

        Schema::create('service_order_checklist_answer_options', function (Blueprint $table) {
            $table->foreignId('answer_id')
                ->constrained('service_order_checklist_answers')
                ->cascadeOnDelete();
            $table->foreignId('option_id')
                ->constrained('service_order_checklist_options')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['answer_id', 'option_id'], 'so_checklist_answer_options_primary');
        });

        Schema::create('service_order_checklist_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('answer_id')
                ->constrained('service_order_checklist_answers')
                ->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'answer_id'], 'so_checklist_photos_answer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_checklist_photos');
        Schema::dropIfExists('service_order_checklist_answer_options');
        Schema::dropIfExists('service_order_checklist_answers');
        Schema::dropIfExists('service_order_checklist_options');
        Schema::dropIfExists('service_order_checklist_questions');
        Schema::dropIfExists('service_order_checklists');
    }
};
