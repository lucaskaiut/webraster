<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Comissões por ordem de serviço.
 *
 * O registro é um snapshot persistido: a listagem não recalcula valores a
 * partir dos serviços. Ordens abertas/em andamento ficam previstas; concluídas
 * passam a finalizadas e canceladas são mantidas apenas para histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedBigInteger('base_amount_cents')->default(0);
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'service_order_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'technician_id', 'status']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }

    private function backfill(): void
    {
        $now = now();

        DB::table('service_orders')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($now): void {
                foreach ($orders as $order) {
                    $items = DB::table('service_order_items as item')
                        ->leftJoin('services as service', 'service.id', '=', 'item.service_id')
                        ->where('item.service_order_id', $order->id)
                        ->get([
                            'item.name',
                            'item.quantity',
                            'item.unit_amount_cents',
                            'item.service_id',
                            'item.commission_type',
                            'item.commission_value',
                            'service.uuid as service_uuid',
                        ]);

                    $baseAmountCents = 0;
                    $amountCents = 0;
                    $breakdown = [];

                    foreach ($items as $item) {
                        $itemTotal = max(0, (int) $item->quantity * (int) $item->unit_amount_cents);
                        $commissionValue = (int) ($item->commission_value ?? 0);
                        $commissionAmount = match ($item->commission_type) {
                            'percentage' => (int) round($itemTotal * $commissionValue / 10000),
                            'fixed' => $commissionValue * max(1, (int) $item->quantity),
                            default => 0,
                        };

                        $baseAmountCents += $itemTotal;
                        $amountCents += $commissionAmount;

                        $breakdown[] = [
                            'service_id' => $item->service_uuid,
                            'service_name' => $item->name,
                            'quantity' => (int) $item->quantity,
                            'base_amount_cents' => $itemTotal,
                            'commission_type' => $item->commission_type,
                            'commission_value' => $commissionValue,
                            'amount_cents' => $commissionAmount,
                        ];
                    }

                    $status = match ($order->status) {
                        'completed' => 'finalized',
                        'cancelled' => 'cancelled',
                        default => 'forecast',
                    };

                    DB::table('commissions')->insert([
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $order->tenant_id,
                        'service_order_id' => $order->id,
                        'technician_id' => $order->technician_id,
                        'status' => $status,
                        'base_amount_cents' => $baseAmountCents,
                        'amount_cents' => $amountCents,
                        'breakdown' => json_encode($breakdown),
                        'finalized_at' => $status === 'finalized'
                            ? ($order->completed_at ?? $now)
                            : null,
                        'created_at' => $order->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
