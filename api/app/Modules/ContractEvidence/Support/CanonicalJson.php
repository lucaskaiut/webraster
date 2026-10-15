<?php

namespace App\Modules\ContractEvidence\Support;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Serialização determinística ("nuvemax-cjson-v1"):
 * - objetos com chaves ordenadas por byte (ksort SORT_STRING), recursivamente;
 * - listas preservam a ordem;
 * - UTF-8 sem escape de unicode/barras, sem espaços;
 * - apenas string, inteiro, booleano e null (números de ponto flutuante são
 *   rejeitados por não terem representação canônica estável);
 * - datas em ISO-8601 UTC com microssegundos (Y-m-d\TH:i:s.u\Z).
 */
final class CanonicalJson
{
    public const NAME = 'nuvemax-cjson-v1';

    public static function encode(array $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public static function hash(array $value): string
    {
        return hash('sha256', self::encode($value));
    }

    public static function timestamp(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return self::timestamp($value);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Valores de ponto flutuante não são permitidos no JSON canônico.');
        }

        if (! is_array($value)) {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                throw new InvalidArgumentException('Strings devem estar em UTF-8.');
            }

            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value, SORT_STRING);

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = self::normalize($item);
        }

        return $normalized;
    }
}
