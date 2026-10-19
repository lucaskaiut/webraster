<?php

use App\Modules\User\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo explícito da conta, para o aplicativo não depender de papéis de ACL
 * para decidir entre a experiência de cliente, técnico ou equipe interna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type', 20)->default(UserType::STAFF->value)->after('client_id');
        });

        // Usuários vinculados a um cliente são do portal do cliente.
        DB::table('users')
            ->whereNotNull('client_id')
            ->update(['type' => UserType::CLIENT->value]);

        // Técnicos têm precedência caso exista algum vínculo residual com cliente.
        if (Schema::hasTable('technician_profiles')) {
            DB::table('users')
                ->whereIn('id', DB::table('technician_profiles')->select('user_id'))
                ->update(['type' => UserType::TECHNICIAN->value]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
