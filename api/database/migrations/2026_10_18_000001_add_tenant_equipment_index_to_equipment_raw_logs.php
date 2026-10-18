<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As consultas de equipment_raw_logs recebem `tenant_id = ?` do TenantScope.
 * Com apenas o índice simples de tenant_id (criado pelo MySQL para a FK), o
 * otimizador o escolhia — ou fazia index_merge com (equipment_id, id) — e lia a
 * tabela inteira a cada posição recebida. O índice composto atende o filtro
 * completo + ORDER BY id e também cobre a FK de tenant_id, tornando o índice
 * simples redundante. Ver docs/incidente-2026-10-18-cpu-mysql-equipment-raw-logs.md.
 */
return new class extends Migration
{
    private const TABLE = 'equipment_raw_logs';

    private const COMPOSITE_INDEX = 'equipment_raw_logs_tenant_equipment_id_index';

    private const TENANT_FK_INDEX = 'equipment_raw_logs_tenant_id_foreign';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->index(['tenant_id', 'equipment_id', 'id'], self::COMPOSITE_INDEX);
        });

        // O MySQL pode descartar sozinho o índice implícito da FK ao surgir o
        // composto; no SQLite ele nem existe.
        if (Schema::hasIndex(self::TABLE, self::TENANT_FK_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::TENANT_FK_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite'
            && ! Schema::hasIndex(self::TABLE, self::TENANT_FK_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('tenant_id', self::TENANT_FK_INDEX);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropIndex(self::COMPOSITE_INDEX);
        });
    }
};
