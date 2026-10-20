<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração de comissão por serviço e snapshot nos itens da OS.
 *
 * commission_type: percentage | fixed
 * commission_value: percentual em centésimos de ponto (100 = 1%) ou valor fixo em centavos
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('commission_type', 20)
                ->nullable()
                ->after('estimated_minutes');

            $table->unsignedInteger('commission_value')
                ->default(0)
                ->after('commission_type');
        });

        Schema::table('service_order_items', function (Blueprint $table) {
            $table->string('commission_type', 20)
                ->nullable()
                ->after('unit_amount_cents');

            $table->unsignedInteger('commission_value')
                ->default(0)
                ->after('commission_type');
        });

        // Itens existentes passam a carregar a configuração vigente do serviço.
        DB::table('service_order_items')
            ->whereNotNull('service_id')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $services = DB::table('services')
                    ->whereIn('id', $items->pluck('service_id')->unique()->all())
                    ->get(['id', 'commission_type', 'commission_value'])
                    ->keyBy('id');

                foreach ($items as $item) {
                    $service = $services->get($item->service_id);

                    if ($service === null) {
                        continue;
                    }

                    DB::table('service_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'commission_type' => $service->commission_type,
                            'commission_value' => $service->commission_value ?? 0,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('service_order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_type', 'commission_value']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['commission_type', 'commission_value']);
        });
    }
};
