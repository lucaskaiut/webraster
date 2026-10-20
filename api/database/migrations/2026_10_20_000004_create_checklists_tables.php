<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos administrativos de checklist usados pelas ordens de serviço.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->text('prompt');
            $table->string('answer_type', 30);
            $table->string('photo_rule', 20);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'checklist_id', 'position'], 'checklist_questions_order_idx');
        });

        Schema::create('checklist_question_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('checklist_question_id')->constrained('checklist_questions')->cascadeOnDelete();
            $table->string('label', 255);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'checklist_question_id', 'position'], 'checklist_options_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_question_options');
        Schema::dropIfExists('checklist_questions');
        Schema::dropIfExists('checklists');
    }
};
