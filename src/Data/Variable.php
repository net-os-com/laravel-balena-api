<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * An environment, config or service variable.
 *
 * The six variable resources balena exposes share this shape and differ only
 * in which resource holds them, so one DTO covers all of them.
 */
final readonly class Variable
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?string $name,
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
            name: Cast::string($attributes['name'] ?? null),
            value: Cast::string($attributes['value'] ?? null),
            raw: $attributes,
        );
    }
}
