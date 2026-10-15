<?php

namespace App\Modules\ContractEvidence\Providers;

use App\Modules\ContractEvidence\Console\Commands\GenerateEvidenceKeyCommand;
use App\Modules\ContractEvidence\Console\Commands\InstallEvidenceGuardsCommand;
use App\Modules\ContractEvidence\Console\Commands\ReconcileContractEvidenceCommand;
use App\Modules\ContractEvidence\Console\Commands\VerifyContractEvidenceCommand;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use Illuminate\Support\ServiceProvider;

class ContractEvidenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EvidenceKeyring::class, fn () => new EvidenceKeyring);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateEvidenceKeyCommand::class,
                InstallEvidenceGuardsCommand::class,
                VerifyContractEvidenceCommand::class,
                ReconcileContractEvidenceCommand::class,
            ]);
        }
    }
}
