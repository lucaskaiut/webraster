<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('require_equipment_loan_acceptance')->default(false)->after('app_secondary_color');
            $table->text('equipment_loan_term')->nullable()->after('require_equipment_loan_acceptance');
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('loan_term_accepted_at')->nullable()->after('execution_notes');
            $table->string('loan_term_signature_path')->nullable()->after('loan_term_accepted_at');
            $table->foreignId('loan_term_collected_by')
                ->nullable()
                ->after('loan_term_signature_path')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('loan_term_text')->nullable()->after('loan_term_collected_by');
            $table->string('loan_term_text_hash', 64)->nullable()->after('loan_term_text');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loan_term_collected_by');
            $table->dropColumn([
                'loan_term_accepted_at',
                'loan_term_signature_path',
                'loan_term_text',
                'loan_term_text_hash',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['require_equipment_loan_acceptance', 'equipment_loan_term']);
        });
    }
};
