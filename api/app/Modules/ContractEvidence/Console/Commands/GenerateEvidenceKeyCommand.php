<?php

namespace App\Modules\ContractEvidence\Console\Commands;

use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use Illuminate\Console\Command;

/**
 * Gera um par Ed25519 e apenas o imprime: nada é gravado em disco ou no
 * repositório. Guarde a chave privada no cofre de segredos do ambiente.
 */
class GenerateEvidenceKeyCommand extends Command
{
    protected $signature = 'contract-evidence:generate-key';

    protected $description = 'Gera um par de chaves Ed25519 para assinar manifestos de evidência de contratos';

    public function handle(): int
    {
        $key = EvidenceKeyring::generate();

        $this->warn('Guarde a chave privada em um cofre de segredos. Ela não será exibida novamente.');
        $this->newLine();
        $this->line('CONTRACT_EVIDENCE_KEY_ID='.$key['key_id']);
        $this->line('CONTRACT_EVIDENCE_PRIVATE_KEY='.$key['secret_key']);
        $this->newLine();
        $this->info('Chave pública (publique e inclua em CONTRACT_EVIDENCE_TRUSTED_KEYS, mantendo as anteriores):');
        $this->line($key['key_id'].':'.$key['public_key']);
        $this->newLine();
        $this->line(EvidenceKeyring::publicKeyPem((string) base64_decode($key['public_key'], true)));

        return self::SUCCESS;
    }
}
