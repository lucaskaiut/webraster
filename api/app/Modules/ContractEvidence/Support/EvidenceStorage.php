<?php

namespace App\Modules\ContractEvidence\Support;

use App\Modules\Client\Models\ClientContract;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Artefatos de evidência no disco privado. Os arquivos são gravados uma vez
 * e nunca sobrescritos; o hash é calculado sobre os bytes exatos gravados.
 */
class EvidenceStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('contract_evidence.disk', 'local'));
    }

    public function isEvidencePath(?string $path): bool
    {
        return $path !== null && str_starts_with($path, $this->directory().'/');
    }

    public function sessionPath(ClientContract $contract, string $sessionUuid, string $file): string
    {
        return $this->base($contract)."/sessions/{$sessionUuid}/{$file}";
    }

    public function signaturePath(ClientContract $contract, string $signatureUuid, string $file): string
    {
        return $this->base($contract)."/signatures/{$signatureUuid}/{$file}";
    }

    /**
     * @return string sha256 dos bytes gravados
     */
    public function write(string $path, string $bytes): string
    {
        $disk = $this->disk();

        if ($disk->exists($path)) {
            throw new \RuntimeException("Artefato de evidência já existe: {$path}");
        }

        if (! $disk->put($path, $bytes)) {
            throw new \RuntimeException("Falha ao gravar artefato de evidência: {$path}");
        }

        $stored = $disk->get($path);

        if ($stored === null || ! hash_equals(hash('sha256', $bytes), hash('sha256', $stored))) {
            throw new \RuntimeException("Artefato de evidência gravado difere do original: {$path}");
        }

        return hash('sha256', $bytes);
    }

    public function read(?string $path): ?string
    {
        if ($path === null || ! $this->disk()->exists($path)) {
            return null;
        }

        return $this->disk()->get($path);
    }

    public function discard(string ...$paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->disk()->delete($path);
            } catch (\Throwable) {
                // Melhor esforço: o reconciliador aponta artefatos órfãos.
            }
        }
    }

    private function directory(): string
    {
        return (string) config('contract_evidence.directory', 'contract-evidence');
    }

    private function base(ClientContract $contract): string
    {
        return $this->directory()."/{$contract->tenant_id}/{$contract->uuid}";
    }
}
