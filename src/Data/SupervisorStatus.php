<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * The supervisor's view of whether a device has settled on its target state.
 *
 * The endpoint also returns a top-level "status" field, which is deliberately
 * not exposed here: balena documents that it reports the HTTP response and
 * "does not indicate the status of the device itself". $appState is the field
 * that answers that question.
 */
final readonly class SupervisorStatus
{
    /**
     * @param  array<int, array<string, mixed>>  $containers
     * @param  array<int, array<string, mixed>>  $images
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?string $appState,
        public ?int $overallDownloadProgress,
        public ?string $release,
        public array $containers = [],
        public array $images = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            appState: Cast::string($attributes['appState'] ?? null),
            overallDownloadProgress: Cast::int($attributes['overallDownloadProgress'] ?? null),
            release: Cast::string($attributes['release'] ?? null),
            containers: self::rows($attributes['containers'] ?? null),
            images: self::rows($attributes['images'] ?? null),
            raw: $attributes,
        );
    }

    /**
     * The device has finished applying its target state.
     */
    public function hasSettled(): bool
    {
        return $this->appState !== null && strcasecmp($this->appState, 'applied') === 0;
    }

    public function isApplying(): bool
    {
        return $this->appState !== null && strcasecmp($this->appState, 'applying') === 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
