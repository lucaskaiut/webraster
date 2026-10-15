<?php

namespace App\Modules\ContractEvidence\Console\Commands;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceEventType;
use App\Modules\ContractEvidence\Enums\EvidenceStatus;
use App\Modules\ContractEvidence\Enums\SigningSessionStatus;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Models\ContractSigningSession;
use App\Modules\ContractEvidence\Services\ContractSigningService;
use App\Modules\ContractEvidence\Services\EvidenceLedger;
use App\Modules\ContractEvidence\Services\EvidenceManifestService;
use App\Modules\ContractEvidence\Support\EvidenceDatabaseGuards;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use Illuminate\Console\Command;

/**
 * Recuperação do fluxo: expira sessões vencidas, libera sessões presas em
 * "completing" (falha entre a reserva e o commit) e assina manifestos que
 * ficaram pendentes por indisponibilidade da chave.
 */
class ReconcileContractEvidenceCommand extends Command
{
    protected $signature = 'contract-evidence:reconcile';

    protected $description = 'Reconcilia sessões de assinatura e protege manifestos de evidência pendentes';

    public function handle(
        ContractSigningService $signing,
        EvidenceManifestService $manifests,
        EvidenceLedger $ledger,
        EvidenceKeyring $keyring,
    ): int {
        $context = RequestEvidence::system();
        $expired = 0;
        $recovered = 0;
        $protected = 0;

        ContractSigningSession::withoutGlobalScopes()
            ->where('status', SigningSessionStatus::OPEN->value)
            ->where('expires_at', '<', now('UTC'))
            ->each(function (ContractSigningSession $session) use ($signing, $context, &$expired): void {
                $signing->expire($session, $context);
                $expired++;
            });

        ContractSigningSession::withoutGlobalScopes()
            ->where('status', SigningSessionStatus::COMPLETING->value)
            ->where('updated_at', '<', now()->subMinutes((int) config('contract_evidence.completing_timeout_minutes', 5)))
            ->each(function (ContractSigningSession $session) use ($ledger, $context, &$recovered): void {
                $hasSignature = ContractSignature::withoutGlobalScopes()->where('session_id', $session->getKey())->exists();
                $status = $hasSignature ? SigningSessionStatus::COMPLETED : SigningSessionStatus::OPEN;

                $updated = ContractSigningSession::withoutGlobalScopes()
                    ->whereKey($session->getKey())
                    ->where('status', SigningSessionStatus::COMPLETING->value)
                    ->update(['status' => $status->value, 'updated_at' => now()]);

                $contract = ClientContract::withoutGlobalScopes()->withTrashed()->find($session->client_contract_id);

                if ($updated > 0 && $contract !== null) {
                    $ledger->record($contract, EvidenceEventType::SESSION_RECOVERED, [
                        'session_uuid' => $session->uuid,
                        'status' => $status->value,
                    ], $context, session: $session);
                    $recovered++;
                }
            });

        $pending = ContractSignature::withoutGlobalScopes()
            ->where('evidence_status', EvidenceStatus::PENDING->value)
            ->count();

        if ($pending > 0 && ! $keyring->hasSigningKey()) {
            $this->warn("{$pending} manifesto(s) aguardando proteção: chave de assinatura não configurada.");
        } else {
            ContractSignature::withoutGlobalScopes()
                ->where('evidence_status', EvidenceStatus::PENDING->value)
                ->each(function (ContractSignature $signature) use ($manifests, $context, &$protected): void {
                    if ($manifests->protect($signature, $context)) {
                        $protected++;
                    }
                });
        }

        if (EvidenceDatabaseGuards::supported() && ! EvidenceDatabaseGuards::installed()) {
            $this->warn('Gatilhos de imutabilidade ausentes: execute contract-evidence:install-guards com um usuário privilegiado.');
        }

        $this->info("Sessões expiradas: {$expired}; recuperadas: {$recovered}; manifestos protegidos: {$protected}.");

        return self::SUCCESS;
    }
}
