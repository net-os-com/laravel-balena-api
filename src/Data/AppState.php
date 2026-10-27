<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * The state of one app on a device, as the supervisor reports it.
 *
 * $updateStatus is where a failed update surfaces. balena documents the values
 * as Done, Downloading, Downloaded, Applying Changes, Rejected and Aborted,
 * but the supervisor's own example returns them lower-cased, so every
 * comparison here is case-insensitive.
 *
 * Requires supervisor 17.7.0 or later for updateStatus, appUuid and the
 * per-service image; older supervisors leave them null.
 */
final readonly class AppState
{
    /**
     * @param  array<string, ServiceState>  $services  Keyed by service name.
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?string $name,
        public ?int $appId,
        public ?string $appUuid,
        public ?string $commit,
        public ?string $updateStatus,
        public array $services = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(string $name, array $attributes): self
    {
        $services = [];

        if (is_array($attributes['services'] ?? null)) {
            foreach ($attributes['services'] as $serviceName => $service) {
                if (is_array($service)) {
                    $services[(string) $serviceName] = ServiceState::fromArray((string) $serviceName, $service);
                }
            }
        }

        return new self(
            name: $name,
            appId: Cast::int($attributes['appId'] ?? null),
            appUuid: Cast::string($attributes['appUuid'] ?? null),
            commit: Cast::string($attributes['commit'] ?? null),
            updateStatus: Cast::string($attributes['updateStatus'] ?? null),
            services: $services,
            raw: $attributes,
        );
    }

    /**
     * The supervisor gave up on, or refused, the target release.
     *
     * "Rejected" means a required service does not meet the requirements to
     * run. These are the only two update statuses that represent a failure.
     */
    public function isFailing(): bool
    {
        return $this->updateStatusIs('rejected', 'aborted');
    }

    public function isRejected(): bool
    {
        return $this->updateStatusIs('rejected');
    }

    public function isAborted(): bool
    {
        return $this->updateStatusIs('aborted');
    }

    /**
     * An update is in flight: downloading, downloaded and waiting on a lock,
     * or being applied.
     */
    public function isUpdating(): bool
    {
        return $this->updateStatusIs('downloading', 'downloaded', 'applying changes');
    }

    public function isDone(): bool
    {
        return $this->updateStatusIs('done');
    }

    /**
     * Services that are neither running nor mid-transition.
     *
     * @return array<string, ServiceState>
     */
    public function stalledServices(): array
    {
        return array_filter(
            $this->services,
            fn (ServiceState $service): bool => ! $service->isRunning() && ! $service->isSettling(),
        );
    }

    private function updateStatusIs(string ...$candidates): bool
    {
        if ($this->updateStatus === null) {
            return false;
        }

        return in_array(strtolower($this->updateStatus), $candidates, true);
    }
}
