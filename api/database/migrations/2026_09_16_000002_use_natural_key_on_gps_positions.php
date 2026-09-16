<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_INDEX = 'gps_positions_tenant_id_traccar_position_id_unique';

    private const NEW_INDEX = 'gps_positions_equipment_recorded_unique';

    /**
     * O forward do Traccar envia a posição antes de o banco atribuir o ID,
     * então `traccar_position_id` chega como 0. A idempotência passa a usar
     * a chave natural (equipamento + horário do dispositivo).
     */
    public function up(): void
    {
        $this->dropIndexIfExists(self::OLD_INDEX);

        $this->removeDuplicates();

        if (! $this->hasIndex(self::NEW_INDEX)) {
            Schema::table('gps_positions', function (Blueprint $table) {
                $table->unique(['equipment_id', 'recorded_at'], self::NEW_INDEX);
            });
        }
    }

    public function down(): void
    {
        // O índice da chave natural atende à FK de equipment_id; cria um
        // índice avulso antes de removê-lo (exigência do InnoDB).
        if (! $this->hasIndex('gps_positions_equipment_id_index')) {
            Schema::table('gps_positions', function (Blueprint $table) {
                $table->index('equipment_id', 'gps_positions_equipment_id_index');
            });
        }

        $this->dropIndexIfExists(self::NEW_INDEX);

        if (! $this->hasIndex(self::OLD_INDEX)) {
            Schema::table('gps_positions', function (Blueprint $table) {
                $table->unique(['tenant_id', 'traccar_position_id']);
            });
        }
    }

    /**
     * Mantém a linha canônica (com traccar_position_id, maior id) e repointa
     * as referências antes de remover as duplicadas.
     */
    private function removeDuplicates(): void
    {
        $duplicates = DB::table('gps_positions')
            ->select('equipment_id', 'recorded_at')
            ->whereNotNull('equipment_id')
            ->groupBy('equipment_id', 'recorded_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $references = [
            'alert_states' => 'last_gps_position_id',
            'alerts' => 'gps_position_id',
            'geofence_events' => 'gps_position_id',
            'vehicle_geofence_states' => 'last_gps_position_id',
        ];

        foreach ($duplicates as $duplicate) {
            $group = fn () => DB::table('gps_positions')
                ->where('equipment_id', $duplicate->equipment_id)
                ->where('recorded_at', $duplicate->recorded_at);

            $keepId = $group()->whereNotNull('traccar_position_id')->orderByDesc('id')->value('id')
                ?? $group()->orderByDesc('id')->value('id');

            $removeIds = $group()->where('id', '!=', $keepId)->pluck('id');

            if ($removeIds->isEmpty()) {
                continue;
            }

            foreach ($references as $table => $column) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn($column, $removeIds)->update([$column => $keepId]);
                }
            }

            DB::table('gps_positions')->whereIn('id', $removeIds)->delete();
        }
    }

    private function hasIndex(string $index): bool
    {
        return collect(Schema::getIndexes('gps_positions'))
            ->pluck('name')
            ->contains($index);
    }

    private function dropIndexIfExists(string $index): void
    {
        if (! $this->hasIndex($index)) {
            return;
        }

        Schema::table('gps_positions', function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });
    }
};
