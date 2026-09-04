<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_group');
        Schema::dropIfExists('groups');

        if (Schema::hasColumn('drivers', 'card_number')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropColumn('card_number');
            });
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->whereIn('permission', [
                    'group.create',
                    'group.read',
                    'group.update',
                    'group.delete',
                ])
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('card_number', 50)->nullable()->after('cnh_expires_at');
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'name', 'type']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('client_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'client_id']);
        });
    }
};
