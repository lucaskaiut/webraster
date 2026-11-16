<?php

namespace App\Modules\Shared\Support;

use Illuminate\Http\Request;

/**
 * Quantidade de registros por página das listagens: um número (1 a 100) ou
 * "all" para trazer tudo numa página só.
 */
final class PerPage
{
    public const ALL = 'all';

    /** Teto do "todos", para listas muito grandes (logs, auditoria) não estourarem a resposta. */
    public const ALL_LIMIT = 5000;

    public static function fromRequest(Request $request, int $default = 15): int|string
    {
        return $request->string('per_page')->toString() === self::ALL
            ? self::ALL
            : (int) $request->integer('per_page', $default);
    }

    public static function size(int|string $perPage, int $max = 100): int
    {
        if ($perPage === self::ALL) {
            return self::ALL_LIMIT;
        }

        return min(max((int) $perPage, 1), $max);
    }
}
