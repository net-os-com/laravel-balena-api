<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * A balena device type, e.g. raspberrypi4-64.
 *
 * An expansion target only: reached through $expand on a device or fleet
 * rather than queried on its own.
 */
final readonly class DeviceType
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?string $slug,
        public ?string $name,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Cast::int($attributes['id'] ?? null),
            slug: Cast::string($attributes['slug'] ?? null),
            name: Cast::string($attributes['name'] ?? null),
            raw: $attributes,
        );
    }
}
