<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * A balena device.
 *
 * Every field is nullable because $select returns partial rows, and the source
 * payload is kept on $raw so expanded relations and any fields this package
 * does not map are never silently lost.
 *
 * Note that $overallStatus is null unless the query explicitly selected it:
 * balena returns overall_status only when asked for by name. DeviceResource's
 * withStatus() scope exists for that reason.
 */
final readonly class Device
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?string $name,
        public ?string $note,
        public ?bool $isActive,
        // Status and connectivity.
        public ?string $status,
        public ?string $overallStatus,
        public ?bool $isOnline,
        public ?string $apiHeartbeatState,
        public ?bool $isConnectedToVpn,
        public ?string $lastConnectivityEvent,
        public ?string $lastVpnEvent,
        // Provisioning and update progress.
        public ?string $provisioningState,
        public ?int $provisioningProgress,
        public ?int $downloadProgress,
        // Host and OS.
        public ?string $osVersion,
        public ?string $osVariant,
        public ?string $supervisorVersion,
        public ?string $ipAddress,
        public ?string $macAddress,
        public ?string $publicAddress,
        public ?bool $isWebAccessible,
        // Health metrics.
        public ?float $cpuUsage,
        public ?float $cpuTemp,
        public ?int $memoryUsage,
        public ?int $memoryTotal,
        public ?int $storageUsage,
        public ?int $storageTotal,
        public ?bool $isUndervolted,
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
            uuid: Cast::string($attributes['uuid'] ?? null),
            name: Cast::string($attributes['device_name'] ?? null),
            note: Cast::string($attributes['note'] ?? null),
            isActive: Cast::bool($attributes['is_active'] ?? null),
            status: Cast::string($attributes['status'] ?? null),
            overallStatus: Cast::string($attributes['overall_status'] ?? null),
            isOnline: Cast::bool($attributes['is_online'] ?? null),
            apiHeartbeatState: Cast::string($attributes['api_heartbeat_state'] ?? null),
            isConnectedToVpn: Cast::bool($attributes['is_connected_to_vpn'] ?? null),
            lastConnectivityEvent: Cast::string($attributes['last_connectivity_event'] ?? null),
            lastVpnEvent: Cast::string($attributes['last_vpn_event'] ?? null),
            provisioningState: Cast::string($attributes['provisioning_state'] ?? null),
            provisioningProgress: Cast::int($attributes['provisioning_progress'] ?? null),
            downloadProgress: Cast::int($attributes['download_progress'] ?? null),
            osVersion: Cast::string($attributes['os_version'] ?? null),
            osVariant: Cast::string($attributes['os_variant'] ?? null),
            supervisorVersion: Cast::string($attributes['supervisor_version'] ?? null),
            ipAddress: Cast::string($attributes['ip_address'] ?? null),
            macAddress: Cast::string($attributes['mac_address'] ?? null),
            publicAddress: Cast::string($attributes['public_address'] ?? null),
            isWebAccessible: Cast::bool($attributes['is_web_accessible'] ?? null),
            cpuUsage: Cast::float($attributes['cpu_usage'] ?? null),
            cpuTemp: Cast::float($attributes['cpu_temp'] ?? null),
            memoryUsage: Cast::int($attributes['memory_usage'] ?? null),
            memoryTotal: Cast::int($attributes['memory_total'] ?? null),
            storageUsage: Cast::int($attributes['storage_usage'] ?? null),
            storageTotal: Cast::int($attributes['storage_total'] ?? null),
            isUndervolted: Cast::bool($attributes['is_undervolted'] ?? null),
            createdAt: Cast::string($attributes['created_at'] ?? null),
            raw: $attributes,
        );
    }

    /**
     * Whether balena considers this device healthy.
     *
     * "Operational" is the only overall_status that means fully working; the
     * rest cover degraded connectivity, provisioning, updates and billing.
     * Returns null when overall_status was not selected, so that "not asked
     * for" stays distinguishable from "not operational".
     */
    public function isOperational(): ?bool
    {
        return $this->overallStatus === null
            ? null
            : strcasecmp($this->overallStatus, 'Operational') === 0;
    }

    /**
     * The fleet this device belongs to, when belongs_to__application was expanded.
     */
    public function fleet(): ?Fleet
    {
        $expanded = Cast::expanded($this->raw['belongs_to__application'] ?? null);

        return $expanded === null ? null : Fleet::fromArray($expanded);
    }

    /**
     * The device type, when is_of__device_type was expanded.
     */
    public function deviceType(): ?DeviceType
    {
        $expanded = Cast::expanded($this->raw['is_of__device_type'] ?? null);

        return $expanded === null ? null : DeviceType::fromArray($expanded);
    }

    /**
     * The release this device is running, when is_running__release was expanded.
     */
    public function runningRelease(): ?Release
    {
        $expanded = Cast::expanded($this->raw['is_running__release'] ?? null);

        return $expanded === null ? null : Release::fromArray($expanded);
    }

    /**
     * The release this device is pinned to, when is_pinned_on__release was expanded.
     */
    public function pinnedRelease(): ?Release
    {
        $expanded = Cast::expanded($this->raw['is_pinned_on__release'] ?? null);

        return $expanded === null ? null : Release::fromArray($expanded);
    }

    /**
     * The release this device should be running, when should_be_running__release
     * was expanded. Differing from runningRelease() means an update is pending.
     */
    public function targetRelease(): ?Release
    {
        $expanded = Cast::expanded($this->raw['should_be_running__release'] ?? null);

        return $expanded === null ? null : Release::fromArray($expanded);
    }

    /**
     * Whether the device is running something other than its target release.
     *
     * Null when either release is missing, so "not expanded" stays
     * distinguishable from "up to date".
     */
    public function hasPendingUpdate(): ?bool
    {
        $running = $this->runningRelease()?->commit;
        $target = $this->targetRelease()?->commit;

        return $running === null || $target === null
            ? null
            : $running !== $target;
    }

    /**
     * This device's tags, keyed by tag key, when device_tag was expanded.
     *
     * An untagged device and an unexpanded query both give an empty array:
     * Pine omits a to-many relation it was not asked for rather than
     * returning it empty, so the two are not distinguishable here.
     *
     * @return array<string, string|null>
     */
    public function tags(): array
    {
        $tags = [];

        foreach ($this->rows('device_tag') as $row) {
            $tag = Tag::fromArray($row);

            if ($tag->key !== null) {
                $tags[$tag->key] = $tag->value;
            }
        }

        return $tags;
    }

    public function tag(string $key): ?string
    {
        return $this->tags()[$key] ?? null;
    }

    /**
     * The environment variables set on this device, keyed by name, when
     * device_environment_variable was expanded.
     *
     * These are the device's own variables. A value the device inherits from
     * its fleet lives on the fleet and is not repeated here, so an empty
     * array does not mean the running container sees no environment.
     *
     * @return array<string, string|null>
     */
    public function environmentVariables(): array
    {
        return $this->variables('device_environment_variable');
    }

    public function environmentVariable(string $name): ?string
    {
        return $this->environmentVariables()[$name] ?? null;
    }

    /**
     * The configuration variables set on this device, keyed by name, when
     * device_config_variable was expanded. These are the RESIN_ and BALENA_
     * settings the supervisor reads.
     *
     * @return array<string, string|null>
     */
    public function configVariables(): array
    {
        return $this->variables('device_config_variable');
    }

    public function configVariable(string $name): ?string
    {
        return $this->configVariables()[$name] ?? null;
    }

    /**
     * The per-service variables set on this device, keyed by service name and
     * then by variable name.
     *
     * These hang off a service install rather than off the device, so they
     * are read through an expanded service_install carrying its own
     * device_service_environment_variable rows.
     *
     * An install whose installs__service was not expanded alongside it is
     * keyed by the service id as a string, so shaping the expand yourself
     * narrows what you can read rather than silently dropping variables.
     *
     * @return array<string, array<string, string|null>>
     */
    public function serviceVariables(): array
    {
        $services = [];

        foreach ($this->rows('service_install') as $install) {
            $service = $this->serviceKey($install);

            foreach ($this->rowsIn($install, 'device_service_environment_variable') as $row) {
                $variable = Variable::fromArray($row);

                if ($variable->name !== null) {
                    $services[$service][$variable->name] = $variable->value;
                }
            }
        }

        return $services;
    }

    public function serviceVariable(string $service, string $name): ?string
    {
        return $this->serviceVariables()[$service][$name] ?? null;
    }

    /**
     * The name/value pairs of an expanded variable relation.
     *
     * @return array<string, string|null>
     */
    private function variables(string $relation): array
    {
        $pairs = [];

        foreach ($this->rows($relation) as $row) {
            $variable = Variable::fromArray($row);

            if ($variable->name !== null) {
                $pairs[$variable->name] = $variable->value;
            }
        }

        return $pairs;
    }

    /**
     * The service an install belongs to: its name when installs__service was
     * expanded, its id otherwise.
     *
     * @param  array<string, mixed>  $install
     */
    private function serviceKey(array $install): string
    {
        $service = $install['installs__service'] ?? null;
        $expanded = Cast::expanded($service);
        $name = $expanded === null ? null : Cast::string($expanded['service_name'] ?? null);

        if ($name !== null) {
            return $name;
        }

        $id = Cast::relationId($service);

        return $id === null ? '' : (string) $id;
    }

    /**
     * The rows of an expanded to-many relation.
     *
     * An empty device and an unexpanded query both give an empty list: Pine
     * omits a relation it was not asked for rather than returning it empty,
     * so the two are not distinguishable here.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $relation): array
    {
        return $this->rowsIn($this->raw, $relation);
    }

    /**
     * The same, on a payload other than this device's own, so the rows nested
     * inside an expansion read like the ones beside it.
     *
     * @param  array<string, mixed>  $source
     * @return list<array<string, mixed>>
     */
    private function rowsIn(array $source, string $relation): array
    {
        $rows = $source[$relation] ?? null;

        if (! is_array($rows)) {
            return [];
        }

        $found = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $found[] = $row;
            }
        }

        return $found;
    }
}
