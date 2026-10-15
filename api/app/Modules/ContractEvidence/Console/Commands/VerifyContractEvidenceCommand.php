<?php

namespace App\Modules\ContractEvidence\Console\Commands;

use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Services\Verification\EvidenceVerifier;
use App\Modules\ContractEvidence\Services\Verification\VerificationReport;
use Illuminate\Console\Command;

/**
 * Códigos de saída: 0 válido, 1 falha de integridade, 2 evidência ausente,
 * 3 inconclusivo.
 */
class VerifyContractEvidenceCommand extends Command
{
    protected $signature = 'contract-evidence:verify
        {signature? : UUID da assinatura (contract_signatures.uuid)}
        {--package= : Caminho de um pacote .zip exportado (verificação sem banco)}
        {--json : Saída em JSON}';

    protected $description = 'Verifica a integridade da cadeia de evidências de uma assinatura de contrato';

    public function handle(EvidenceVerifier $verifier): int
    {
        $package = $this->option('package');

        if (is_string($package) && $package !== '') {
            $report = $verifier->verifyPackage($package);
        } else {
            $uuid = (string) $this->argument('signature');
            $signature = ContractSignature::withoutGlobalScopes()->where('uuid', $uuid)->first();

            if ($signature === null) {
                $this->error('Assinatura não encontrada.');

                return 2;
            }

            $report = $verifier->verifyStored($signature);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Verificação', 'Resultado', 'Detalhe'], $report->checks());
            $this->line('Resultado: '.$report->status());
        }

        return match ($report->status()) {
            VerificationReport::VALID => self::SUCCESS,
            VerificationReport::INTEGRITY_FAILURE => 1,
            VerificationReport::MISSING => 2,
            default => 3,
        };
    }
}
