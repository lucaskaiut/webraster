<?php

namespace App\Modules\Client\Support;

use App\Modules\Client\Support\Facades\ClientContext;

/**
 * Helpers de autorização por cliente compartilhados por Policies.
 */
final class ClientAuthorization
{
    /**
     * Quando o contexto de cliente não está ativo (usuário staff),
     * não restringe por cliente.
     */
    public static function allowsClient(?int $resourceClientId): bool
    {
        if (! ClientContext::isResolved()) {
            return true;
        }

        return $resourceClientId !== null
            && ClientContext::clientId() === $resourceClientId;
    }

    public static function isRestricted(): bool
    {
        return ClientContext::isResolved();
    }
}
