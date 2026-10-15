<?php

namespace App\Modules\ContractEvidence\Services\Verification;

use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Services\EvidenceLedger;
use App\Modules\ContractEvidence\Services\EvidenceManifestService;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use App\Modules\ContractEvidence\Support\EvidenceStorage;
use JsonException;
use ZipArchive;

/**
 * Verifica a cadeia de evidências de uma assinatura, a partir do banco ou de
 * um pacote exportado (sem acesso ao banco). Cada verificação resulta em
 * válido, falha de integridade, ausente ou inconclusivo.
 */
class EvidenceVerifier
{
    public const ARTIFACT_KEYS = ['presented_document', 'signed_document', 'signature_image'];

    public function __construct(
        private readonly EvidenceKeyring $keyring,
        private readonly EvidenceStorage $storage,
    ) {}

    public function verifyStored(ContractSignature $signature): VerificationReport
    {
        $report = new VerificationReport($signature->uuid);

        $report->add(
            'manifest_hash',
            hash_equals($signature->manifest_sha256, hash('sha256', $signature->manifest))
                ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            'SHA-256 do manifesto gravado comparado ao hash registrado.',
        );

        $events = ContractEvidenceEvent::withoutGlobalScopes()
            ->where('client_contract_id', $signature->client_contract_id)
            ->orderBy('sequence')
            ->get()
            ->map(fn (ContractEvidenceEvent $event) => EvidenceLedger::export($event))
            ->all();

        $artifacts = [
            'presented_document' => $this->storage->read($signature->presented_document_path),
            'signed_document' => $this->storage->read($signature->document_path),
            'signature_image' => $this->storage->read($signature->signature_image_path),
        ];

        $envelope = $signature->manifest_signature !== null ? [
            'algorithm' => EvidenceKeyring::ALGORITHM,
            'key_id' => $signature->key_id,
            'signature' => $signature->manifest_signature,
        ] : null;

        $manifest = $this->verifyMaterial($report, $signature->manifest, $envelope, $artifacts, $events, null);

        if ($manifest !== null) {
            $this->checkRecordBinding($report, $signature, $manifest);
        }

        return $report;
    }

    public function verifyPackage(string $zipPath): VerificationReport
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return (new VerificationReport)->add('package', VerificationReport::MISSING, 'Pacote de evidências ilegível.');
        }

        try {
            $manifestBytes = $zip->getFromName('manifest.json');

            if ($manifestBytes === false) {
                return (new VerificationReport)->add('manifest', VerificationReport::MISSING, 'manifest.json ausente no pacote.');
            }

            $envelope = $this->decode($zip->getFromName('manifest.sig.json') ?: null);
            $events = $this->decode($zip->getFromName('events.json') ?: null);
            $artifacts = [];

            foreach (self::ARTIFACT_KEYS as $key) {
                $artifacts[$key] = $this->findArtifact($zip, $key);
            }

            $decoded = $this->decode($manifestBytes);
            $report = new VerificationReport(is_array($decoded) ? ($decoded['signature_id'] ?? null) : null);

            $embeddedKey = is_array($envelope) && isset($envelope['public_key'])
                ? base64_decode((string) $envelope['public_key'], true) ?: null
                : null;

            $this->verifyMaterial(
                $report,
                $manifestBytes,
                is_array($envelope) ? $envelope : null,
                $artifacts,
                is_array($events) ? $events : null,
                $embeddedKey,
            );

            return $report;
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<string, mixed>|null  $envelope
     * @param  array<string, string|null>  $artifacts
     * @param  list<array<string, mixed>>|null  $events
     * @return array<string, mixed>|null manifesto decodificado
     */
    private function verifyMaterial(
        VerificationReport $report,
        string $manifestBytes,
        ?array $envelope,
        array $artifacts,
        ?array $events,
        ?string $embeddedPublicKey,
    ): ?array {
        $manifest = $this->decode($manifestBytes);

        if (! is_array($manifest)
            || ($manifest['format'] ?? null) !== EvidenceManifestService::FORMAT
            || ! is_int($manifest['version'] ?? null)) {
            $report->add('manifest_format', VerificationReport::INTEGRITY_FAILURE, 'Manifesto inválido ou em formato desconhecido.');

            return null;
        }

        if ($manifest['version'] > EvidenceManifestService::VERSION) {
            $report->add('manifest_format', VerificationReport::INCONCLUSIVE, 'Versão de manifesto mais nova que este verificador.');

            return $manifest;
        }

        $report->add(
            'manifest_canonical',
            CanonicalJson::encode($manifest) === $manifestBytes ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            'Manifesto reserializado de forma canônica deve ser idêntico aos bytes gravados.',
        );

        $this->checkManifestSignature($report, $manifestBytes, $envelope, $embeddedPublicKey);

        foreach (self::ARTIFACT_KEYS as $key) {
            $this->checkArtifact($report, $key, $manifest['artifacts'][$key] ?? null, $artifacts[$key] ?? null);
        }

        $this->checkEvents($report, $manifest, $events);

        return $manifest;
    }

    private function checkManifestSignature(VerificationReport $report, string $manifestBytes, ?array $envelope, ?string $embeddedPublicKey): void
    {
        if ($envelope === null || blank($envelope['signature'] ?? null)) {
            $report->add('manifest_signature', VerificationReport::INCONCLUSIVE, 'Manifesto ainda sem assinatura do servidor (proteção pendente).');

            return;
        }

        if (($envelope['algorithm'] ?? null) !== EvidenceKeyring::ALGORITHM) {
            $report->add('manifest_signature', VerificationReport::INCONCLUSIVE, 'Algoritmo de assinatura não suportado.');

            return;
        }

        $keyId = (string) ($envelope['key_id'] ?? '');
        $trusted = $this->keyring->publicKey($keyId);
        $publicKey = $trusted ?? $embeddedPublicKey;

        if ($publicKey === null) {
            $report->add('manifest_signature', VerificationReport::INCONCLUSIVE, "Chave pública {$keyId} desconhecida.");

            return;
        }

        if (! EvidenceKeyring::verify($manifestBytes, (string) $envelope['signature'], $publicKey)) {
            $report->add('manifest_signature', VerificationReport::INTEGRITY_FAILURE, "Assinatura Ed25519 do manifesto inválida para a chave {$keyId}.");

            return;
        }

        if ($trusted === null) {
            $report->add('manifest_signature', VerificationReport::INCONCLUSIVE, "Assinatura confere com a chave embutida no pacote, mas {$keyId} não está no chaveiro confiável.");

            return;
        }

        $report->add('manifest_signature', VerificationReport::VALID, "Assinatura Ed25519 válida (chave {$keyId}).");
    }

    private function checkArtifact(VerificationReport $report, string $key, mixed $declared, ?string $bytes): void
    {
        if (! is_array($declared)) {
            if ($bytes !== null) {
                $report->add("artifact:{$key}", VerificationReport::INTEGRITY_FAILURE, 'Arquivo presente sem declaração no manifesto.');
            }

            return;
        }

        if ($bytes === null) {
            $report->add("artifact:{$key}", VerificationReport::MISSING, 'Arquivo declarado no manifesto não foi encontrado.');

            return;
        }

        $matches = hash_equals((string) ($declared['sha256'] ?? ''), hash('sha256', $bytes))
            && (! isset($declared['size']) || (int) $declared['size'] === strlen($bytes));

        $report->add(
            "artifact:{$key}",
            $matches ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            $matches ? 'SHA-256 confere com o manifesto.' : 'SHA-256 (ou tamanho) diverge do manifesto.',
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>>|null  $events
     */
    private function checkEvents(VerificationReport $report, array $manifest, ?array $events): void
    {
        if ($events === null) {
            $report->add('events_chain', VerificationReport::MISSING, 'Trilha de eventos ausente.');

            return;
        }

        $previous = null;
        $expectedSequence = 1;
        $byHash = [];

        foreach ($events as $event) {
            $valid = is_array($event)
                && (int) ($event['sequence'] ?? 0) === $expectedSequence
                && ($event['previous_hash'] ?? null) === $previous
                && hash_equals((string) ($event['hash'] ?? ''), EvidenceLedger::hashOf($event));

            if (! $valid) {
                $report->add('events_chain', VerificationReport::INTEGRITY_FAILURE, "Cadeia de eventos quebrada na sequência {$expectedSequence}.");

                return;
            }

            $byHash[$event['hash']] = $event;
            $previous = $event['hash'];
            $expectedSequence++;
        }

        $report->add('events_chain', VerificationReport::VALID, sprintf('%d eventos encadeados conferidos.', count($events)));

        $items = $manifest['events']['items'] ?? [];
        $covered = $items !== [] && collect($items)->every(
            fn ($item) => isset($byHash[$item['hash'] ?? ''])
                && (int) $byHash[$item['hash']]['sequence'] === (int) ($item['sequence'] ?? 0)
                && $byHash[$item['hash']]['type'] === ($item['type'] ?? null),
        );

        $headHash = $manifest['events']['head_hash'] ?? null;

        $report->add(
            'events_binding',
            $covered && isset($byHash[$headHash]) ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            'Eventos referenciados no manifesto existem na trilha com o mesmo hash.',
        );

        $completed = collect($events)->first(
            fn ($event) => $event['type'] === 'signature_completed'
                && ($event['data']['signature_uuid'] ?? null) === ($manifest['signature_id'] ?? null),
        );

        $manifestSha = hash('sha256', CanonicalJson::encode($manifest));

        $report->add(
            'events_completion',
            $completed !== null && ($completed['data']['manifest_sha256'] ?? null) === $manifestSha
                ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            'Evento de conclusão registra o hash deste manifesto.',
        );

        if (in_array($manifest['method'] ?? null, ['app_signature', 'legacy_app_signature'], true)) {
            $this->checkSigningSequence($report, $manifest, $items);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function checkSigningSequence(VerificationReport $report, array $manifest, array $items): void
    {
        $expected = ['signing_session_started'];

        if (($manifest['consent']['explicit'] ?? false) === true) {
            $expected[] = 'consent_accepted';
        }

        array_push($expected, 'signature_received', 'document_sealed');

        $types = array_values(array_intersect(array_column($items, 'type'), $expected));

        $report->add(
            'events_sequence',
            $types === $expected ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            'Sessão iniciada, consentimento, recebimento da assinatura e selagem na ordem esperada.',
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function checkRecordBinding(VerificationReport $report, ContractSignature $signature, array $manifest): void
    {
        $matches = ($manifest['signature_id'] ?? null) === $signature->uuid
            && ($manifest['contract']['id'] ?? null) === $signature->client_contract_uuid
            && ($manifest['artifacts']['signed_document']['sha256'] ?? null) === $signature->document_sha256
            && ($manifest['artifacts']['signature_image']['sha256'] ?? null) === $signature->signature_image_sha256
            && ($manifest['artifacts']['presented_document']['sha256'] ?? null) === $signature->presented_document_sha256
            && ($manifest['events']['head_hash'] ?? null) === $signature->events_head_hash;

        $report->add(
            'record_binding',
            $matches ? VerificationReport::VALID : VerificationReport::INTEGRITY_FAILURE,
            'Campos do registro conferem com o manifesto.',
        );
    }

    private function findArtifact(ZipArchive $zip, string $key): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, "artifacts/{$key}.")) {
                $bytes = $zip->getFromIndex($i);

                return $bytes === false ? null : $bytes;
            }
        }

        return null;
    }

    private function decode(?string $json): mixed
    {
        if ($json === null) {
            return null;
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
}
