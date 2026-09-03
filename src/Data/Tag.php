<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * A device, fleet or release tag.
 */
final readonly class Tag
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?string $key,
        public ?string $value,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Cast::int($attributes['id'] ?? null),
            key: Cast::string($attributes['tag_key'] ?? null),
            value: Cast::string($attributes['value'] ?? null),
            raw: $attributes,
        );
    }
}
