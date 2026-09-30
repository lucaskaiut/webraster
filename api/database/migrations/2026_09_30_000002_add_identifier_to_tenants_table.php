<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('identifier', 60)->nullable()->unique()->after('name');
        });

        DB::table('tenants')
            ->whereNull('identifier')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $tenant): void {
                $base = Str::limit(Str::slug($tenant->name) ?: 'empresa', 50, '');
                $identifier = $base;
                $suffix = 2;

                while (DB::table('tenants')->where('identifier', $identifier)->exists()) {
                    $identifier = "{$base}-{$suffix}";
                    $suffix++;
                }

                DB::table('tenants')->where('id', $tenant->id)->update(['identifier' => $identifier]);
            });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['identifier']);
            $table->dropColumn('identifier');
        });
    }
};
