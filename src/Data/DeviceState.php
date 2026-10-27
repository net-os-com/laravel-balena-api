<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * What a device's supervisor reports about the apps it is running.
 *
 * This is the closest thing balena offers to a per-device error: the resource
 * API has no error field, and per-service status is not queryable in bulk.
 * Fetching it costs one proxied call per device and only works while the
 * device is online.
 */
final readonly class DeviceState
{
    /**
     * @param  array<string, AppState>  $apps  Keyed by app name.
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public array $apps = [],
        public array $raw = [],
    ) {}

    /**
     * The supervisor returns a map of app name to app state, with no envelope.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $apps = [];

        foreach ($attributes as $name => $app) {
            if (is_array($app)) {
                $apps[(string) $name] = AppState::fromArray((string) $name, $app);
            }
        }

        return new self($apps, $attributes);
    }

    public function app(string $name): ?AppState
    {
        return $this->apps[$name] ?? null;
    }

    /**
     * Most devices run a single app, so this is usually the one you want.
     */
    public function firstApp(): ?AppState
    {
        foreach ($this->apps as $app) {
            return $app;
        }

        return null;
    }

    /**
     * The first app's update status, which for a single-app device is the
     * device's update status.
     */
    public function updateStatus(): ?string
    {
        return $this->firstApp()?->updateStatus;
    }

    /**
     * Any app whose update was rejected or aborted.
     */
    public function isFailing(): bool
    {
        foreach ($this->apps as $app) {
            if ($app->isFailing()) {
                return true;
            }
        }

        return false;
    }

    public function isUpdating(): bool
    {
        foreach ($this->apps as $app) {
            if ($app->isUpdating()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every service across every app, flattened.
     *
     * @return array<int, ServiceState>
     */
    public function services(): array
    {
        $services = [];

        foreach ($this->apps as $app) {
            foreach ($app->services as $service) {
                $services[] = $service;
            }
        }

        return $services;
    }

    /**
     * Services that are neither running nor mid-transition, across every app.
     *
     * @return array<int, ServiceState>
     */
    public function stalledServices(): array
    {
        $stalled = [];

        foreach ($this->apps as $app) {
            foreach ($app->stalledServices() as $service) {
                $stalled[] = $service;
            }
        }

        return $stalled;
    }
}
