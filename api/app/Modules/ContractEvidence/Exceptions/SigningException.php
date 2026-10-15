<?php

namespace App\Modules\ContractEvidence\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Recusa ou falha do fluxo de assinatura com um código estável para o app
 * decidir a ação (reiniciar sessão, corrigir dados, tentar novamente).
 */
class SigningException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }

    public static function sessionExpired(): self
    {
        return new self('session_expired', 'A sessão de assinatura expirou. Abra o contrato novamente para assinar.', 410);
    }

    public static function sessionRevoked(): self
    {
        return new self('session_revoked', 'Esta sessão de assinatura não é mais válida. Abra o contrato novamente para assinar.', 410);
    }

    public static function inProgress(): self
    {
        return new self('in_progress', 'A assinatura já está sendo processada. Aguarde alguns instantes.');
    }

    public static function documentChanged(): self
    {
        return new self('document_changed', 'O contrato foi alterado desde que foi aberto. Leia a nova versão antes de assinar.');
    }

    public static function alreadySigned(): self
    {
        return new self('already_signed', 'Este contrato já está assinado.');
    }

    public static function conflictingResubmission(): self
    {
        return new self('already_completed', 'Esta sessão já foi concluída com outra assinatura.');
    }

    public static function consentRequired(): self
    {
        return new self('consent_required', 'É necessário aceitar o termo de consentimento exibido para assinar.', 422);
    }

    public static function persistenceFailed(): self
    {
        return new self(
            'persistence_failed',
            'Não foi possível concluir a assinatura. Nenhuma assinatura foi registrada; tente novamente.',
            503,
        );
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->reason,
            'errors' => (object) [],
        ], $this->status);
    }
}
