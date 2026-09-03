<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * A balena release.
 */
final readonly class Release
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?string $commit,
        public ?int $id,
        public ?string $status,
        public ?string $source,
        public ?string $semver,
        public ?int $revision,
        public ?bool $isFinal,
        public ?bool $isInvalidated,
        public ?int $fleetId,
        public ?string $createdAt,
        public ?string $startTimestamp,
        public ?string $endTimestamp,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            commit: Cast::string($attributes['commit'] ?? null),
            id: Cast::int($attributes['id'] ?? null),
            status: Cast::string($attributes['status'] ?? null),
            source: Cast::string($attributes['source'] ?? null),
            semver: Cast::string($attributes['semver'] ?? null),
            revision: Cast::int($attributes['revision'] ?? null),
            isFinal: Cast::bool($attributes['is_final'] ?? null),
            isInvalidated: Cast::bool($attributes['is_invalidated'] ?? null),
            fleetId: Cast::relationId($attributes['belongs_to__application'] ?? null),
            createdAt: Cast::string($attributes['created_at'] ?? null),
            startTimestamp: Cast::string($attributes['start_timestamp'] ?? null),
            endTimestamp: Cast::string($attributes['end_timestamp'] ?? null),
            raw: $attributes,
        );
    }

    /**
     * The fleet this release belongs to, when belongs_to__application was expanded.
     */
    public function fleet(): ?Fleet
    {
        $expanded = Cast::expanded($this->raw['belongs_to__application'] ?? null);

        return $expanded === null ? null : Fleet::fromArray($expanded);
    }
}
