<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('type');
            $table->foreignId('created_by')->nullable()->after('source')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('clicked_at')->nullable()->after('read_at');

            $table->index(['tenant_id', 'source', 'created_at']);
        });

        // Notificações existentes vieram do motor de alertas.
        DB::table('user_notifications')
            ->whereNull('source')
            ->update(['source' => 'alert']);
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['tenant_id', 'source', 'created_at']);
            $table->dropColumn(['source', 'clicked_at']);
        });
    }
};
