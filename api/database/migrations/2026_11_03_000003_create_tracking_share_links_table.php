<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_share_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Hash SHA-256 do token público; o token só é exibido na criação.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('tracking_share_link_vehicle', function (Blueprint $table) {
            $table->foreignId('tracking_share_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->primary(['tracking_share_link_id', 'vehicle_id']);
        });

        // Administradores já existentes passam a compartilhar o monitoramento.
        Role::query()
            ->where('name', DefaultRole::ADMINISTRATOR->value)
            ->each(fn (Role $role) => $role->grantPermissions(Permission::TRACKING_SHARE));
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_share_link_vehicle');
        Schema::dropIfExists('tracking_share_links');
    }
};
