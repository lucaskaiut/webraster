<?php

use App\Modules\Alert\Services\AlertConfigService;
use App\Modules\Client\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * O modelo de alertas passa a ser por cliente (portal): cria as configurações
 * desligadas de cada tipo para todos os clientes existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Client::query()
            ->withoutGlobalScopes()
            ->orderBy('id')
            ->chunkById(100, function ($clients): void {
                foreach ($clients as $client) {
                    app(AlertConfigService::class)->ensureClientDefaults($client);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
