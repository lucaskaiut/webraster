<?php

use App\Modules\User\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Move os dados do cadastro separado de técnico para o próprio usuário.
 *
 * A jornada passa a pertencer ao usuário (user_work_hours) e o endereço-base
 * fica nas colunas de endereço de users. A tabela technician_profiles deixa
 * de existir, eliminando o relacionamento técnico ↔ usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('zip', 9)->nullable()->after('type');
            $table->string('street')->nullable()->after('zip');
            $table->string('number', 20)->nullable()->after('street');
            $table->string('complement')->nullable()->after('number');
            $table->string('neighborhood')->nullable()->after('complement');
            $table->string('city')->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('state');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('technician_active')->default(true)->after('longitude');
        });

        Schema::create('user_work_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // 0 = domingo … 6 = sábado (Carbon::dayOfWeek).
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['user_id', 'weekday']);
        });

        if (Schema::hasTable('technician_profiles')) {
            $this->migrateTechnicianData();
        }

        Schema::dropIfExists('technician_work_hours');
        Schema::dropIfExists('technician_profiles');
    }

    public function down(): void
    {
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
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['technician_profile_id', 'weekday']);
        });

        $technicians = DB::table('users')
            ->where('type', UserType::TECHNICIAN->value)
            ->whereNull('deleted_at')
            ->get();

        foreach ($technicians as $technician) {
            $profileId = DB::table('technician_profiles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $technician->tenant_id,
                'user_id' => $technician->id,
                'zip' => $technician->zip,
                'street' => $technician->street,
                'number' => $technician->number,
                'complement' => $technician->complement,
                'neighborhood' => $technician->neighborhood,
                'city' => $technician->city,
                'state' => $technician->state,
                'latitude' => $technician->latitude,
                'longitude' => $technician->longitude,
                'is_active' => $technician->technician_active,
                'created_at' => $technician->created_at,
                'updated_at' => $technician->updated_at,
            ]);

            $hours = DB::table('user_work_hours')->where('user_id', $technician->id)->get();

            foreach ($hours as $hour) {
                DB::table('technician_work_hours')->insert([
                    'technician_profile_id' => $profileId,
                    'weekday' => $hour->weekday,
                    'starts_at' => $hour->starts_at,
                    'ends_at' => $hour->ends_at,
                    'created_at' => $hour->created_at,
                    'updated_at' => $hour->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('user_work_hours');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'zip',
                'street',
                'number',
                'complement',
                'neighborhood',
                'city',
                'state',
                'latitude',
                'longitude',
                'technician_active',
            ]);
        });
    }

    private function migrateTechnicianData(): void
    {
        $profiles = DB::table('technician_profiles')->get();

        foreach ($profiles as $profile) {
            DB::table('users')->where('id', $profile->user_id)->update([
                'zip' => $profile->zip,
                'street' => $profile->street,
                'number' => $profile->number,
                'complement' => $profile->complement,
                'neighborhood' => $profile->neighborhood,
                'city' => $profile->city,
                'state' => $profile->state,
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
                'technician_active' => $profile->is_active,
            ]);

            $hours = DB::table('technician_work_hours')
                ->where('technician_profile_id', $profile->id)
                ->get();

            foreach ($hours as $hour) {
                DB::table('user_work_hours')->insert([
                    'user_id' => $profile->user_id,
                    'weekday' => $hour->weekday,
                    'starts_at' => $hour->starts_at,
                    'ends_at' => $hour->ends_at,
                    'created_at' => $hour->created_at,
                    'updated_at' => $hour->updated_at,
                ]);
            }
        }
    }
};
