<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('service', 80);
            $table->string('method', 10);
            $table->text('url');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('succeeded')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error', 500)->nullable();
            $table->json('request_headers')->nullable();
            $table->mediumText('request_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->mediumText('response_body')->nullable();
            // Rota, comando ou job que originou a chamada.
            $table->string('origin', 255)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->index();

            $table->index(['service', 'created_at']);
        });

        // Administradores do umbrella passam a ver o painel de APIs externas.
        Role::query()
            ->where('name', DefaultRole::ADMINISTRATOR->value)
            ->whereIn('tenant_id', Tenant::query()->whereNull('parent_id')->select('id'))
            ->each(fn (Role $role) => $role->grantPermissions(Permission::EXTERNAL_API_VIEW));
    }

    public function down(): void
    {
        Schema::dropIfExists('external_api_requests');
    }
};
