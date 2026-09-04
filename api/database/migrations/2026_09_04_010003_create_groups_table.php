<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Grupos foram removidos do produto. Mantido como no-op para não quebrar
 * histórico de migrations em ambientes que já rodaram a versão anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
