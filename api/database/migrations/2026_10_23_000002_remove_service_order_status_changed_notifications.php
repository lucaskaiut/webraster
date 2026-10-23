<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove da caixa de entrada (painel e app) as notificações antigas de
        // atualização de status da OS. Os pushes já entregues não podem ser
        // recolhidos do aparelho, mas deixam de ser enviados.
        DB::table('user_notifications')
            ->where('type', 'service_order.status_changed')
            ->delete();
    }

    public function down(): void
    {
        // Notificações removidas não são recriadas.
    }
};
