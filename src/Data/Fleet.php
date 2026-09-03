<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * A balena fleet. The underlying Pine resource is named "application".
 */
final readonly class Fleet
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $slug,
        public ?string $uuid,
        public ?bool $isPublic,
        public ?bool $isHost,
        public ?bool $isArchived,
        public ?int $deviceTypeId,
        public ?int $organizationId,
        public ?string $createdAt,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Cast::int($attributes['id'] ?? null),
            name: Cast::string($attributes['app_name'] ?? null),
            slug: Cast::string($attributes['slug'] ?? null),
            uuid: Cast::string($attributes['uuid'] ?? null),
            isPublic: Cast::bool($attributes['is_public'] ?? null),
            isHost: Cast::bool($attributes['is_host'] ?? null),
            isArchived: Cast::bool($attributes['is_archived'] ?? null),
            deviceTypeId: Cast::relationId($attributes['is_for__device_type'] ?? null),
            organizationId: Cast::relationId($attributes['organization'] ?? null),
            createdAt: Cast::string($attributes['created_at'] ?? null),
            raw: $attributes,
        );
    }

    /**
     * The device type, when is_for__device_type was expanded.
     */
    public function deviceType(): ?DeviceType
    {
        $expanded = Cast::expanded($this->raw['is_for__device_type'] ?? null);

        return $expanded === null ? null : DeviceType::fromArray($expanded);
    }
}
