<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->json('geometry')->nullable();
            $table->decimal('bbox_min_lat', 10, 7)->nullable();
            $table->decimal('bbox_max_lat', 10, 7)->nullable();
            $table->decimal('bbox_min_lng', 10, 7)->nullable();
            $table->decimal('bbox_max_lng', 10, 7)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'client_id', 'is_active']);
            $table->index(['tenant_id', 'bbox_min_lat', 'bbox_max_lat'], 'geofences_tenant_bbox_lat_index');
            $table->index(['tenant_id', 'bbox_min_lng', 'bbox_max_lng'], 'geofences_tenant_bbox_lng_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
