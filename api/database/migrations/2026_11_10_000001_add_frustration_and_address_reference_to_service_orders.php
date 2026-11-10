<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->text('address_reference')->nullable()->after('address');
            $table->text('frustration_reason')->nullable()->after('cancellation_reason');
            $table->foreignId('frustrated_by')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
            $table->timestamp('frustrated_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('frustrated_by');
            $table->dropColumn(['address_reference', 'frustration_reason', 'frustrated_at']);
        });
    }
};
