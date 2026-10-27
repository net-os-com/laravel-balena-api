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
        $rows = $this->raw['device_tag'] ?? null;

        if (! is_array($rows)) {
            return [];
        }

        $tags = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            /** @var array<string, mixed> $row */
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
}
