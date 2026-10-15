<?php

namespace App\Modules\ContractEvidence\Console\Commands;

use App\Modules\ContractEvidence\Support\EvidenceDatabaseGuards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Instala os gatilhos de imutabilidade das tabelas de evidência. Use uma
 * conexão com privilégio suficiente (ex.: --connection=mysql_admin) quando
 * o usuário da aplicação não puder criar gatilhos.
 */
class InstallEvidenceGuardsCommand extends Command
{
    protected $signature = 'contract-evidence:install-guards {--connection= : Conexão do config/database.php a utilizar}';

    protected $description = 'Instala os gatilhos que bloqueiam alteração/exclusão das evidências de assinatura';

    public function handle(): int
    {
        $connection = DB::connection($this->option('connection') ?: null);

        if (! EvidenceDatabaseGuards::supported($connection)) {
            $this->warn('Driver sem suporte a gatilhos de evidência; a proteção fica na camada de modelo.');

            return self::SUCCESS;
        }

        try {
            EvidenceDatabaseGuards::install($connection);
        } catch (Throwable $e) {
            $this->error('Falha ao criar os gatilhos: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Gatilhos de imutabilidade instalados.');

        return self::SUCCESS;
    }
}
