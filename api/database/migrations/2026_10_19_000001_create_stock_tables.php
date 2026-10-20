<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoques operacionais, vínculo de equipamentos/técnicos e o livro de
 * movimentações. Cada equipamento continua sendo um item único (IMEI); não
 * existe quantidade no cadastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->foreignId('stock_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('stocks')
                ->nullOnDelete();

            $table->index(['tenant_id', 'stock_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('stock_id')
                ->nullable()
                ->after('technician_active')
                ->constrained('stocks')
                ->nullOnDelete();

            $table->index(['tenant_id', 'stock_id']);
        });

        Schema::create('equipment_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('from_stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->foreignId('to_stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->string('type', 24);
            $table->text('description');
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(
                ['tenant_id', 'equipment_id', 'occurred_at'],
                'equipment_stock_movements_equipment_occurred_idx',
            );
            $table->index(['tenant_id', 'from_stock_id'], 'equipment_stock_movements_from_stock_idx');
            $table->index(['tenant_id', 'to_stock_id'], 'equipment_stock_movements_to_stock_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_stock_movements');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'stock_id']);
            $table->dropConstrainedForeignId('stock_id');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'stock_id']);
            $table->dropConstrainedForeignId('stock_id');
        });

        Schema::dropIfExists('stocks');
    }
};
