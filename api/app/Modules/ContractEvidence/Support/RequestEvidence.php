<?php

namespace App\Modules\ContractEvidence\Support;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Contexto técnico de uma requisição, separando o que o servidor observou
 * (IP de conexão, horário, request_id, autenticação) do que o cliente
 * apenas declarou (user-agent, versão e plataforma do app).
 */
final class RequestEvidence
{
    private const REQUEST_ID_ATTRIBUTE = 'contract_evidence_request_id';

    public function __construct(
        public readonly string $requestId,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly ?string $appVersion,
        public readonly ?string $appPlatform,
        public readonly ?string $authMethod,
        public readonly ?int $authTokenId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $requestId = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (! is_string($requestId)) {
            $requestId = (string) Str::uuid();
            $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);
        }

        [$method, $tokenId] = self::authentication($request->user());

        return new self(
            requestId: $requestId,
            ip: $request->ip(),
            userAgent: self::clean($request->userAgent(), 512),
            appVersion: self::declared($request->header('X-App-Version')),
            appPlatform: self::declared($request->header('X-App-Platform')),
            authMethod: $method,
            authTokenId: $tokenId,
        );
    }

    public static function system(): self
    {
        return new self((string) Str::uuid(), null, 'system', null, null, 'system', null);
    }

    /**
     * Dados de contexto sem PII além do IP/user-agent, para eventos e manifesto.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'app_version' => $this->appVersion,
            'app_platform' => $this->appPlatform,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private static function authentication(mixed $user): array
    {
        if (! $user instanceof User) {
            return [null, null];
        }

        $token = $user->currentAccessToken();

        $tokenKey = $token instanceof PersonalAccessToken ? $token->getKey() : null;

        if (is_numeric($tokenKey) && (int) $tokenKey > 0) {
            return ['sanctum_personal_access_token', (int) $tokenKey];
        }

        if ($token instanceof TransientToken) {
            return ['sanctum_session_cookie', null];
        }

        return ['sanctum', null];
    }

    private static function declared(?string $value): ?string
    {
        $value = self::clean($value, 40);

        return $value !== null && preg_match('/^[\w.\-+ ()\/]+$/u', $value) === 1 ? $value : null;
    }

    private static function clean(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
