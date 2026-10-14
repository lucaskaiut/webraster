<?php

namespace App\Modules\Shared\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ordenação por múltiplas colunas no formato `coluna:asc,coluna2:desc`.
 *
 * Apenas colunas da lista de permitidas são aplicadas (evita ordenação por
 * colunas arbitrárias). Sem critérios válidos, aplica a ordenação padrão.
 */
final class QuerySort
{
    /**
     * @param  Builder<*>  $query
     * @param  list<string>  $allowed
     * @param  array<string, string>  $expressions  Colunas que usam expressão SQL (ex.: subconsulta) em vez da coluna direta.
     * @return Builder<*>
     */
    public static function apply(
        Builder $query,
        ?string $sort,
        array $allowed,
        string $default = 'created_at:desc',
        array $expressions = [],
    ): Builder {
        $criteria = self::parse($sort);
        $appliedColumns = [];
        $lastDirection = 'desc';
        $applied = false;

        foreach ($criteria as $criterion) {
            if (! in_array($criterion['column'], $allowed, true)) {
                continue;
            }

            $expression = $expressions[$criterion['column']] ?? null;

            if (is_string($expression) && $expression !== '') {
                // A direção já vem normalizada (asc|desc) e a expressão é
                // definida pelo backend, não pelo usuário.
                $query->orderByRaw("{$expression} {$criterion['direction']}");
            } else {
                $query->orderBy($criterion['column'], $criterion['direction']);
            }

            $appliedColumns[] = $criterion['column'];
            $lastDirection = $criterion['direction'];
            $applied = true;
        }

        if (! $applied) {
            [$column, $direction] = array_pad(explode(':', $default, 2), 2, 'desc');

            if (! self::isSafeColumn($column)) {
                return $query;
            }

            $lastDirection = $direction === 'asc' ? 'asc' : 'desc';
            $query->orderBy($column, $lastDirection);
        }

        // Desempate estável entre registros com os mesmos valores ordenados.
        if (! in_array('id', $appliedColumns, true)) {
            $query->orderBy('id', $lastDirection);
        }

        return $query;
    }

    /**
     * @return list<array{column: string, direction: 'asc'|'desc'}>
     */
    public static function parse(?string $sort): array
    {
        if (blank($sort)) {
            return [];
        }

        $criteria = [];

        foreach (explode(',', $sort) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            [$column, $direction] = array_pad(explode(':', $part, 2), 2, 'asc');

            if (! self::isSafeColumn($column)) {
                continue;
            }

            $criteria[] = [
                'column' => trim($column),
                'direction' => strtolower(trim((string) $direction)) === 'desc' ? 'desc' : 'asc',
            ];

            if (count($criteria) >= 5) {
                break;
            }
        }

        return $criteria;
    }

    private static function isSafeColumn(?string $column): bool
    {
        return is_string($column) && preg_match('/^[A-Za-z0-9_.]+$/', trim($column)) === 1;
    }
}
