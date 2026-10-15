<?php

namespace App\Modules\ContractEvidence\Support;

use RuntimeException;
use SensitiveParameter;

/**
 * Chaves Ed25519 (libsodium) usadas para assinar e verificar manifestos.
 *
 * A chave privada vem do ambiente (variável ou arquivo de secret) e nunca é
 * exposta. As chaves públicas confiáveis formam o chaveiro de verificação:
 * após uma rotação, mantenha as antigas para validar manifestos históricos.
 */
class EvidenceKeyring
{
    public const ALGORITHM = 'ed25519';

    private ?string $secretKey = null;

    private ?string $activeKeyId = null;

    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param  array<string, mixed>|null  $config  null = lê config('contract_evidence.signing') a cada uso
     */
    public function __construct(private readonly ?array $fixedConfig = null) {}

    public static function keyIdFor(string $publicKey): string
    {
        return 'ed25519-'.substr(hash('sha256', $publicKey), 0, 16);
    }

    /**
     * @return array{key_id: string, secret_key: string, public_key: string}
     */
    public static function generate(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $public = sodium_crypto_sign_publickey($keypair);

        return [
            'key_id' => self::keyIdFor($public),
            'secret_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
            'public_key' => base64_encode($public),
        ];
    }

    public function hasSigningKey(): bool
    {
        $this->load();

        return $this->secretKey !== null;
    }

    public function activeKeyId(): ?string
    {
        $this->load();

        return $this->activeKeyId;
    }

    /**
     * @return array{key_id: string, signature: string}
     */
    public function sign(string $message): array
    {
        $this->load();

        if ($this->secretKey === null || $this->activeKeyId === null) {
            throw new RuntimeException('Chave de assinatura de evidências não configurada.');
        }

        return [
            'key_id' => $this->activeKeyId,
            'signature' => base64_encode(sodium_crypto_sign_detached($message, $this->secretKey)),
        ];
    }

    /**
     * Chaves públicas confiáveis (binário) por key_id.
     *
     * @return array<string, string>
     */
    public function trustedPublicKeys(): array
    {
        $this->load();

        $keys = [];

        foreach (array_filter(array_map('trim', explode(',', (string) ($this->config['trusted_public_keys'] ?? '')))) as $entry) {
            [$keyId, $encoded] = array_pad(explode(':', $entry, 2), 2, '');
            $public = base64_decode($encoded, true);

            if ($keyId !== '' && $public !== false && strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $keys[$keyId] = $public;
            }
        }

        if ($this->secretKey !== null && $this->activeKeyId !== null) {
            $keys[$this->activeKeyId] = sodium_crypto_sign_publickey_from_secretkey($this->secretKey);
        }

        return $keys;
    }

    public function publicKey(string $keyId): ?string
    {
        return $this->trustedPublicKeys()[$keyId] ?? null;
    }

    public static function verify(string $message, string $signatureBase64, string $publicKey): bool
    {
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /**
     * Chave pública em PEM (SubjectPublicKeyInfo) para verificação com OpenSSL.
     */
    public static function publicKeyPem(string $publicKey): string
    {
        $der = hex2bin('302a300506032b6570032100').$publicKey;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function load(): void
    {
        $this->config = $this->fixedConfig ?? (array) config('contract_evidence.signing', []);
        $this->secretKey = null;
        $this->activeKeyId = null;

        $encoded = $this->config['private_key'] ?? null;
        $path = $this->config['private_key_path'] ?? null;

        if (blank($encoded) && filled($path) && is_readable($path)) {
            $encoded = trim((string) file_get_contents($path));
        }

        if (blank($encoded)) {
            return;
        }

        $this->secretKey = $this->decodeSecret((string) $encoded);
        $public = sodium_crypto_sign_publickey_from_secretkey($this->secretKey);
        $this->activeKeyId = filled($this->config['key_id'] ?? null)
            ? (string) $this->config['key_id']
            : self::keyIdFor($public);
    }

    private function decodeSecret(#[SensitiveParameter] string $encoded): string
    {
        $raw = base64_decode($encoded, true);

        if ($raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return $raw;
        }

        if ($raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($raw));
        }

        throw new RuntimeException('CONTRACT_EVIDENCE_PRIVATE_KEY inválida: esperado base64 de 32 ou 64 bytes.');
    }
}
