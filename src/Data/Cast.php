<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * Coercion helpers shared by the DTOs.
 *
 * balena returns numbers as strings in places and omits unselected fields
 * entirely, so hydration stays lenient rather than throwing on shape drift.
 */
final class Cast
{
    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function string(mixed $value): ?string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : (is_numeric($value) ? (bool) $value : null);
    }

    /**
     * Unwrap an expanded relation.
     *
     * Pine returns expansions as a list even for to-one links, and as a bare
     * id when the relation was not expanded.
     *
     * @return array<string, mixed>|null
     */
    public static function expanded(mixed $value): ?array
    {
        if (is_array($value) && array_is_list($value)) {
            $first = $value[0] ?? null;

            return is_array($first) ? $first : null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * The id of a linked resource, whether it came back expanded or as a
     * bare foreign key.
     */
    public static function relationId(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $expanded = self::expanded($value);

        return $expanded === null ? null : self::int($expanded['id'] ?? null);
    }
}
