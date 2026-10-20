<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_command_logs', function (Blueprint $table): void {
            $table->foreignId('service_order_id')
                ->nullable()
                ->after('equipment_id')
                ->constrained('service_orders')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'service_order_id', 'requested_at'],
                'device_command_logs_order_requested_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('device_command_logs', function (Blueprint $table): void {
            $table->dropIndex('device_command_logs_order_requested_idx');
            $table->dropConstrainedForeignId('service_order_id');
        });
    }
};
