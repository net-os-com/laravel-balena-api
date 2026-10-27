<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * The state of one service on a device, as the supervisor reports it.
 *
 * The supervisor documentation does not enumerate the full set of status
 * values — observed examples are "Running", "Downloading", "Downloaded",
 * "Installing", "Starting", "Stopping" and "Idle" — so this deliberately
 * exposes the raw string rather than an enum that would be guesswork.
 */
final readonly class ServiceState
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?string $name,
        public ?string $status,
        public ?string $image,
        public ?int $releaseId,
        public ?int $downloadProgress,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(string $name, array $attributes): self
    {
        return new self(
            name: $name,
            status: Cast::string($attributes['status'] ?? null),
            image: Cast::string($attributes['image'] ?? null),
            releaseId: Cast::int($attributes['releaseId'] ?? null),
            downloadProgress: Cast::int($attributes['downloadProgress'] ?? null),
            raw: $attributes,
        );
    }

    public function isRunning(): bool
    {
        return $this->status !== null && strcasecmp($this->status, 'Running') === 0;
    }

    /**
     * Whether the supervisor is still moving this service towards its target.
     */
    public function isSettling(): bool
    {
        return $this->status !== null && in_array(
            strtolower($this->status),
            ['downloading', 'downloaded', 'installing', 'starting', 'stopping'],
            true,
        );
    }
}
