<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * A balena device.
 *
 * Every field is nullable because $select returns partial rows, and the source
 * payload is kept on $raw so expanded relations and any fields this package
 * does not map are never silently lost.
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
        public ?bool $isOnline,
        public ?string $status,
        public ?bool $isActive,
        public ?string $osVersion,
        public ?string $osVariant,
        public ?string $supervisorVersion,
        public ?string $ipAddress,
        public ?string $macAddress,
        public ?string $publicAddress,
        public ?string $overallStatus,
        public ?int $overallProgress,
        public ?bool $isWebAccessible,
        public ?string $lastConnectivityEvent,
        public ?string $createdAt,
        public ?string $modifiedAt,
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
            isOnline: Cast::bool($attributes['is_online'] ?? null),
            status: Cast::string($attributes['status'] ?? null),
            isActive: Cast::bool($attributes['is_active'] ?? null),
            osVersion: Cast::string($attributes['os_version'] ?? null),
            osVariant: Cast::string($attributes['os_variant'] ?? null),
            supervisorVersion: Cast::string($attributes['supervisor_version'] ?? null),
            ipAddress: Cast::string($attributes['ip_address'] ?? null),
            macAddress: Cast::string($attributes['mac_address'] ?? null),
            publicAddress: Cast::string($attributes['public_address'] ?? null),
            overallStatus: Cast::string($attributes['overall_status'] ?? null),
            overallProgress: Cast::int($attributes['overall_progress'] ?? null),
            isWebAccessible: Cast::bool($attributes['is_web_accessible'] ?? null),
            lastConnectivityEvent: Cast::string($attributes['last_connectivity_event'] ?? null),
            createdAt: Cast::string($attributes['created_at'] ?? null),
            modifiedAt: Cast::string($attributes['modified_at'] ?? null),
            raw: $attributes,
        );
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
}
