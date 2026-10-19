<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agendamento: coordenadas do cliente, perfil e jornada dos técnicos,
 * estimativas de deslocamento/execução na OS e anexos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('zip');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // Endereço usado na última geocodificação: coordenadas são refeitas quando ele muda.
            $table->string('geocoded_address', 500)->nullable()->after('longitude');
        });

        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('zip', 9)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('technician_work_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_profile_id')->constrained('technician_profiles')->cascadeOnDelete();
            // 0 = domingo … 6 = sábado (Carbon::dayOfWeek).
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['technician_profile_id', 'weekday']);
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('technician_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('scheduled_end_at');
            $table->unsignedInteger('estimated_travel_minutes')->nullable()->after('estimated_duration_minutes');
            $table->unsignedInteger('travel_distance_meters')->nullable()->after('estimated_travel_minutes');
            $table->timestamp('reminder_sent_at')->nullable()->after('travel_distance_meters');

            $table->index(['scheduled_start_at', 'reminder_sent_at']);
        });

        Schema::create('service_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_attachments');

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex(['scheduled_start_at', 'reminder_sent_at']);
            $table->dropColumn([
                'address',
                'latitude',
                'longitude',
                'estimated_duration_minutes',
                'estimated_travel_minutes',
                'travel_distance_meters',
                'reminder_sent_at',
            ]);
        });

        Schema::dropIfExists('technician_work_hours');
        Schema::dropIfExists('technician_profiles');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geocoded_address']);
        });
    }
};
