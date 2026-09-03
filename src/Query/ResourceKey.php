<?php

declare(strict_types=1);

namespace NetOs\Balena\Query;

use InvalidArgumentException;
use NetOs\Balena\Query\Filter\ValueFormatter;

/**
 * Renders the three single-resource lookup forms the balena API documents:
 *
 *   /device(123)
 *   /device(uuid='deadbeef')
 *   /device_tag(device=123,tag_key='KEY')
 *
 * Every mutating Action requires one of these. A PATCH or DELETE aimed at a
 * collection with a $filter would mutate every matching row, so writes are
 * never allowed to be addressed by filter alone.
 */
final readonly class ResourceKey
{
    private function __construct(private string $key) {}

    /**
     * Build a key from an id, a UUID string, or an attribute map.
     *
     * A bare string is treated as a UUID, which is how devices are addressed
     * almost everywhere in balena.
     *
     * @param  int|string|array<string, mixed>  $key
     */
    public static function make(int|string|array $key): self
    {
        if (is_array($key)) {
            return self::fromAttributes($key);
        }

        if (is_int($key) || ctype_digit($key)) {
            return self::fromId((int) $key);
        }

        return self::fromAttributes(['uuid' => $key]);
    }

    public static function fromId(int $id): self
    {
        return new self("({$id})");
    }

    /**
     * @param  array<array-key, mixed>  $attributes  Keyed by field name.
     */
    public static function fromAttributes(array $attributes): self
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('A resource key needs at least one attribute.');
        }

        $parts = [];

        foreach ($attributes as $field => $value) {
            if (! is_string($field) || $field === '') {
                throw new InvalidArgumentException(
                    'Resource key attributes must be keyed by field name; received a '
                    .get_debug_type($field).' key.'
                );
            }

            $parts[] = $field.'='.ValueFormatter::format($value);
        }

        return new self('('.implode(',', $parts).')');
    }

    /**
     * Append this key to a resource name, e.g. "device(uuid='abc')".
     */
    public function appliedTo(string $resource): string
    {
        return $resource.$this->key;
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
