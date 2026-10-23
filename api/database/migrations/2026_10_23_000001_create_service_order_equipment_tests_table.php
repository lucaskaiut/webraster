<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_equipment_tests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_command_log_id')
                ->nullable()
                ->constrained('device_command_logs')
                ->nullOnDelete();
            $table->string('action', 30);
            $table->string('command_type', 80)->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_order_id', 'requested_at'],
                'service_order_equipment_tests_order_idx',
            );
        });

        // Preserva os testes de bloqueio/desbloqueio já registrados como logs
        // de comando, para que o histórico das OSs continue completo. Apenas
        // execuções bem-sucedidas entram na OS.
        DB::table('device_command_logs')
            ->whereNotNull('service_order_id')
            ->whereIn('command_type', ['engineStop', 'engineResume'])
            ->where('status', 'success')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                $rows = [];

                foreach ($logs as $log) {
                    $rows[] = [
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $log->tenant_id,
                        'service_order_id' => $log->service_order_id,
                        'equipment_id' => $log->equipment_id,
                        'user_id' => $log->user_id,
                        'device_command_log_id' => $log->id,
                        'action' => $log->command_type === 'engineStop' ? 'block' : 'unblock',
                        'command_type' => $log->command_type,
                        'status' => $log->status,
                        'result' => $log->response,
                        'requested_at' => $log->requested_at,
                        'executed_at' => $log->executed_at,
                        'created_at' => $log->created_at,
                        'updated_at' => $log->updated_at,
                    ];
                }

                DB::table('service_order_equipment_tests')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_equipment_tests');
    }
};
