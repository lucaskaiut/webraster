<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('entity_type')->nullable()->after('action');
            $table->string('entity_id')->nullable()->after('entity_type');
            $table->json('details')->nullable()->after('entity_id');

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropColumn(['entity_type', 'entity_id', 'details']);
        });
    }
};
