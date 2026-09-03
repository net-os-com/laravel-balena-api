<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * The installation of a service on a device.
 *
 * An expansion target only. It matters mainly because device service variables
 * are keyed by service install rather than by device.
 */
final readonly class ServiceInstall
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?int $deviceId,
        public ?int $serviceId,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Cast::int($attributes['id'] ?? null),
            deviceId: Cast::relationId($attributes['device'] ?? null),
            serviceId: Cast::relationId($attributes['installs__service'] ?? null),
            raw: $attributes,
        );
    }
}
