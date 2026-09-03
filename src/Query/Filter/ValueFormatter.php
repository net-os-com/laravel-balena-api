<?php

declare(strict_types=1);

namespace NetOs\Balena\Query\Filter;

use BackedEnum;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Renders PHP values as OData literals for use inside a $filter.
 */
final class ValueFormatter
{
    public static function format(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::formatFloat($value),
            $value instanceof DateTimeInterface => self::formatDateTime($value),
            $value instanceof BackedEnum => self::format($value->value),
            is_string($value) => self::formatString($value),
            default => throw new InvalidArgumentException(
                'Cannot format value of type ['.get_debug_type($value).'] as an OData literal.'
            ),
        };
    }

    /**
     * Single-quoted, with embedded quotes doubled per the OData literal rules.
     */
    private static function formatString(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    private static function formatFloat(float $value): string
    {
        // Avoid locale-dependent separators and scientific notation.
        return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Timestamps are normalised to UTC and emitted with the OData datetime
     * prefix.
     *
     * NOTE: unverified against a live balena instance. Pine may accept a bare
     * ISO-8601 string instead. If filtering on created_at/modified_at returns
     * an error, this is the single place to change.
     */
    private static function formatDateTime(DateTimeInterface $value): string
    {
        $utc = (new \DateTimeImmutable($value->format('Y-m-d\TH:i:s.uP')))
            ->setTimezone(new DateTimeZone('UTC'));

        return "datetime'".$utc->format('Y-m-d\TH:i:s\Z')."'";
    }
}
