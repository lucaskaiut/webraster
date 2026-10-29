<?php

namespace App\Modules\ACL\Enums;

use Illuminate\Http\Request;

/**
 * Painéis (SPAs) que consomem a API. Cada um se identifica pelo header
 * X-Panel e exige a permissão de acesso correspondente. Requisições sem o
 * header (app mobile, integrações via token) não são restringidas.
 */
enum Panel: string
{
    case WEB = 'web';
    case SCHEDULING = 'agendamento';

    public const HEADER = 'X-Panel';

    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom(strtolower(trim((string) $request->header(self::HEADER))));
    }

    public function permission(): Permission
    {
        return match ($this) {
            self::WEB => Permission::PANEL_WEB_ACCESS,
            self::SCHEDULING => Permission::PANEL_SCHEDULING_ACCESS,
        };
    }

    public function deniedMessage(): string
    {
        return 'Seu usuário não tem acesso a este painel.';
    }
}
