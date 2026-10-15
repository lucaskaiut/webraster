<?php

namespace App\Modules\ContractEvidence\Services;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceEventType;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Services\Verification\EvidenceVerifier;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use App\Modules\ContractEvidence\Support\EvidenceStorage;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use RuntimeException;
use ZipArchive;

/**
 * Pacote de evidências verificável sem acesso ao sistema: manifesto (bytes
 * exatos), assinatura do manifesto, chave pública, trilha de eventos e os
 * artefatos (documento apresentado, documento assinado, imagem).
 */
class EvidencePackageExporter
{
    public function __construct(
        private readonly EvidenceStorage $storage,
        private readonly EvidenceKeyring $keyring,
        private readonly EvidenceVerifier $verifier,
        private readonly EvidenceLedger $ledger,
    ) {}

    /**
     * @return string caminho do zip temporário (o chamador remove)
     */
    public function export(ContractSignature $signature, ?RequestEvidence $context = null, ?int $actorId = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'evidence-');

        if ($path === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário do pacote.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o pacote de evidências.');
        }

        $zip->addFromString('manifest.json', $signature->manifest);

        $publicKey = $signature->key_id !== null ? $this->keyring->publicKey($signature->key_id) : null;

        $zip->addFromString('manifest.sig.json', CanonicalJson::encode([
            'algorithm' => EvidenceKeyring::ALGORITHM,
            'key_id' => $signature->key_id,
            'manifest_sha256' => $signature->manifest_sha256,
            'protected_at' => CanonicalJson::timestamp($signature->protected_at),
            'public_key' => $publicKey !== null ? base64_encode($publicKey) : null,
            'signature' => $signature->manifest_signature,
        ]));

        if ($signature->manifest_signature !== null) {
            $zip->addFromString('manifest.sig', (string) base64_decode($signature->manifest_signature, true));
        }

        if ($publicKey !== null) {
            $zip->addFromString('public-key.pem', EvidenceKeyring::publicKeyPem($publicKey));
        }

        $events = ContractEvidenceEvent::withoutGlobalScopes()
            ->where('client_contract_id', $signature->client_contract_id)
            ->orderBy('sequence')
            ->get()
            ->map(fn (ContractEvidenceEvent $event) => EvidenceLedger::export($event))
            ->all();

        $zip->addFromString('events.json', CanonicalJson::encode($events));

        foreach ([
            'presented_document' => [$signature->presented_document_path, 'html'],
            'signed_document' => [$signature->document_path, null],
            'signature_image' => [$signature->signature_image_path, null],
        ] as $key => [$artifactPath, $extension]) {
            $bytes = $this->storage->read($artifactPath);

            if ($bytes !== null) {
                $extension ??= pathinfo((string) $artifactPath, PATHINFO_EXTENSION) ?: 'bin';
                $zip->addFromString("artifacts/{$key}.{$extension}", $bytes);
            }
        }

        $zip->addFromString(
            'verification.json',
            json_encode($this->verifier->verifyStored($signature)->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        $zip->addFromString('LEIA-ME.txt', $this->readme($signature));
        $zip->close();

        $contract = ClientContract::withoutGlobalScopes()->withTrashed()->find($signature->client_contract_id);

        if ($contract !== null) {
            $this->ledger->record($contract, EvidenceEventType::EVIDENCE_EXPORTED, [
                'signature_uuid' => $signature->uuid,
                'package_sha256' => hash_file('sha256', $path),
            ], $context ?? RequestEvidence::system(), $actorId, signature: $signature);
        }

        return $path;
    }

    private function readme(ContractSignature $signature): string
    {
        return <<<TXT
        Pacote de evidências da assinatura {$signature->uuid}

        Conteúdo
        - manifest.json: manifesto canônico (nuvemax-cjson-v1). Os bytes exatos são os assinados.
        - manifest.sig / manifest.sig.json: assinatura Ed25519 do manifesto e identificador da chave.
        - public-key.pem: chave pública correspondente. Confirme-a com a chave publicada pela empresa.
        - events.json: trilha de eventos encadeada por SHA-256 (cada evento referencia o hash do anterior).
        - artifacts/: documento apresentado (HTML), documento assinado (PDF) e imagem da assinatura.
        - verification.json: resultado da verificação feita pelo sistema no momento da exportação.

        Verificação independente (OpenSSL 3+)
        1. Assinatura do manifesto:
           openssl pkeyutl -verify -pubin -inkey public-key.pem -rawin -in manifest.json -sigfile manifest.sig
        2. Integridade dos arquivos: compare "sha256sum artifacts/*" com manifest.json → artifacts.*.sha256.
        3. Trilha: recalcule o hash de cada evento conforme docs (ou use php artisan contract-evidence:verify --package=).

        Limites
        A assinatura desenhada é um registro gráfico, não uma assinatura digital com certificado. Os dados do
        signatário (nome, CPF, nascimento) foram declarados por ele; a conta autenticada e o contexto técnico
        (IP, horário do servidor, dispositivo) são evidências de atribuição, não prova absoluta de identidade.
        TXT;
    }
}
