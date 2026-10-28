<?php

declare(strict_types=1);

namespace NetOs\Balena\Resources;

use NetOs\Balena\Actions\Devices\DeactivateDevice;
use NetOs\Balena\Actions\Devices\DeleteDevice;
use NetOs\Balena\Actions\Devices\MoveDeviceToFleet;
use NetOs\Balena\Actions\Devices\PinDeviceToRelease;
use NetOs\Balena\Actions\Devices\RenameDevice;
use NetOs\Balena\Actions\Devices\SetDeviceNote;
use NetOs\Balena\Actions\Supervisor\BlinkDevice;
use NetOs\Balena\Actions\Supervisor\GetDeviceState;
use NetOs\Balena\Actions\Supervisor\GetSupervisorStatus;
use NetOs\Balena\Actions\Supervisor\PurgeDeviceData;
use NetOs\Balena\Actions\Supervisor\RebootDevice;
use NetOs\Balena\Actions\Supervisor\RestartFleetServices;
use NetOs\Balena\Actions\Supervisor\RestartService;
use NetOs\Balena\Data\Device;
use NetOs\Balena\Data\DeviceState;
use NetOs\Balena\Data\SupervisorStatus;
use NetOs\Balena\Query\PineQuery;

/**
 * @mixin PineQuery
 */
class DeviceResource extends Resource
{
    /**
     * The fields worth selecting when you want a device's current condition.
     *
     * overall_status is the reason this exists: balena returns it only when a
     * query names it explicitly, so a plain get() leaves it null and makes a
     * healthy device look statusless.
     *
     * @var list<string>
     */
    public const STATUS_FIELDS = [
        'id',
        'uuid',
        'device_name',
        'status',
        'overall_status',
        'is_online',
        'api_heartbeat_state',
        'is_connected_to_vpn',
        'last_connectivity_event',
        'last_vpn_event',
        'provisioning_state',
        'provisioning_progress',
        'download_progress',
        'os_version',
        'supervisor_version',
        'cpu_usage',
        'cpu_temp',
        'memory_usage',
        'memory_total',
        'storage_usage',
        'storage_total',
        'is_undervolted',
    ];

    /**
     * What a device *is*, as opposed to how it is doing right now: identity,
     * host OS, networking and the operator's note.
     *
     * Kept apart from STATUS_FIELDS because these barely change, so a query
     * that polls status every minute has no reason to carry them.
     *
     * @var list<string>
     */
    public const DETAIL_FIELDS = [
        'note',
        'is_active',
        'os_version',
        'os_variant',
        'supervisor_version',
        'ip_address',
        'mac_address',
        'public_address',
        'is_web_accessible',
        'created_at',
    ];

    /**
     * The release fields worth expanding next to a device: enough to tell two
     * releases apart and to show one to a human.
     *
     * @var list<string>
     */
    public const RELEASE_FIELDS = [
        'id',
        'commit',
        'raw_version',
        'status',
        'created_at',
    ];

    /**
     * What to select on an expanded variable: a name, a value, and the id you
     * need to address the row again.
     *
     * @var list<string>
     */
    public const VARIABLE_FIELDS = [
        'id',
        'name',
        'value',
    ];

    public function resourceName(): string
    {
        return 'device';
    }

    public function dto(): ?string
    {
        return Device::class;
    }

    // ------------------------------------------------------------------ reads

    public function byUuid(string $uuid): ?Device
    {
        /** @var Device|null */
        return $this->query()->find(['uuid' => $uuid]);
    }

    public function byId(int $id): ?Device
    {
        /** @var Device|null */
        return $this->query()->find($id);
    }

    /**
     * Devices belonging to a fleet, addressed by slug or numeric id.
     */
    public function inFleet(int|string $fleet): PineQuery
    {
        if (is_int($fleet) || ctype_digit($fleet)) {
            return $this->query()->where('belongs_to__application', (int) $fleet);
        }

        return $this->query()->whereRelation(
            'belongs_to__application',
            fn (PineQuery $query): PineQuery => $query->where('slug', $fleet),
        );
    }

    /**
     * Select the status and health fields, including overall_status, which
     * balena otherwise omits.
     *
     * Compose it with the other scopes in either order:
     *
     *   Balena::devices()->withStatus()->where('is_online', false)->get();
     *   Balena::devices()->inFleet('org/fleet')->select(...DeviceResource::STATUS_FIELDS)->get();
     */
    public function withStatus(): PineQuery
    {
        return $this->query()->select(...self::STATUS_FIELDS);
    }

    /**
     * Everything this package maps about a device, in one request: the status
     * and health fields, the host, network and note fields, the running,
     * target and pinned releases, the device's tags and its variables.
     *
     * Pine returns only the fields a query names, so anything left unselected
     * arrives as null and reads like missing data. This scope is the answer
     * to that for callers who want the whole device rather than a slice.
     *
     *   $device = Balena::devices()->withDetails()->where('uuid', $uuid)->first();
     *   $device->osVersion; $device->ipAddress; $device->tags();
     *   $device->runningRelease()?->rawVersion;
     */
    public function withDetails(): PineQuery
    {
        return $this->detailsOn($this->query());
    }

    /**
     * byUuid() with every mapped field selected and the releases and tags
     * expanded. One request, no nulls for things balena knows.
     */
    public function detailsByUuid(string $uuid): ?Device
    {
        /** @var Device|null */
        return $this->withDetails()->where('uuid', $uuid)->first();
    }

    /**
     * Devices in a fleet, fully populated. The bulk counterpart of
     * detailsByUuid(), and a much heavier query than statusInFleet(): every
     * tag and every variable of every device comes back with it.
     */
    public function detailsInFleet(int|string $fleet): PineQuery
    {
        return $this->detailsOn($this->inFleet($fleet));
    }

    /**
     * A device's own variables alongside the device, in one request: the
     * environment, configuration and per-service variables the dashboard
     * shows under Variables.
     *
     *   $device = Balena::devices()->withVariables()->where('uuid', $uuid)->first();
     *   $device->environmentVariables();
     *   $device->configVariable('BALENA_HOST_CONFIG_gpu_mem');
     *   $device->serviceVariable('mqtt', 'BROKER_URL');
     *
     * Only variables set on the device itself. balena keeps fleet-wide values
     * on the fleet and lets a device override them, so a device with no
     * variables of its own still runs with the fleet's. withDetails() already
     * includes these; this scope is for when the variables are all you want.
     */
    public function withVariables(): PineQuery
    {
        return $this->variablesOn($this->query());
    }

    /**
     * Devices a fleet is responsible for, with their status fields selected
     * and the fleet's target release expanded so a pending update is visible.
     *
     * Deliberately lighter than detailsInFleet(): id and commit are all a
     * pending-update check needs, and this is the query you poll.
     */
    public function statusInFleet(int|string $fleet): PineQuery
    {
        return $this->inFleet($fleet)
            ->select(...self::STATUS_FIELDS)
            ->expand('is_running__release', fn (PineQuery $query): PineQuery => $query->select('id', 'commit'))
            ->expand('should_be_running__release', fn (PineQuery $query): PineQuery => $query->select('id', 'commit'));
    }

    public function online(): PineQuery
    {
        return $this->query()->where('is_online', true);
    }

    public function offline(): PineQuery
    {
        return $this->query()->where('is_online', false);
    }

    /**
     * Apply the full selection and expansions to an existing query, so the
     * scopes that start from a filter can reuse them.
     */
    private function detailsOn(PineQuery $query): PineQuery
    {
        return $this->variablesOn(
            $query
                ->select(...self::STATUS_FIELDS, ...self::DETAIL_FIELDS)
                ->expand('is_running__release', fn (PineQuery $release): PineQuery => $release->select(...self::RELEASE_FIELDS))
                ->expand('should_be_running__release', fn (PineQuery $release): PineQuery => $release->select(...self::RELEASE_FIELDS))
                ->expand('is_pinned_on__release', fn (PineQuery $release): PineQuery => $release->select(...self::RELEASE_FIELDS))
                ->expand('device_tag', fn (PineQuery $tag): PineQuery => $tag->select('tag_key', 'value'))
        );
    }

    /**
     * Expand the three variable relations that hang off a device.
     *
     * The service expand inside the third one is what lets the variables be
     * keyed by service name rather than by service id: balena identifies a
     * device service variable by device, service and name, and the service
     * link carries nothing but the id until it is expanded.
     */
    private function variablesOn(PineQuery $query): PineQuery
    {
        return $query
            ->expand('device_environment_variable', fn (PineQuery $variable): PineQuery => $variable->select(...self::VARIABLE_FIELDS))
            ->expand('device_config_variable', fn (PineQuery $variable): PineQuery => $variable->select(...self::VARIABLE_FIELDS))
            ->expand('device_service_environment_variable', fn (PineQuery $variable): PineQuery => $variable
                ->select(...self::VARIABLE_FIELDS)
                ->expand('service', fn (PineQuery $service): PineQuery => $service->select('id', 'service_name')));
    }

    // ----------------------------------------------------------------- writes

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function rename(int|string|array $device, string $name): void
    {
        $this->action(RenameDevice::class)($device, $name);
    }

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function setNote(int|string|array $device, ?string $note): void
    {
        $this->action(SetDeviceNote::class)($device, $note);
    }

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function moveToFleet(int|string|array $device, int $fleetId): void
    {
        $this->action(MoveDeviceToFleet::class)($device, $fleetId);
    }

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function pinToRelease(int|string|array $device, int $releaseId): void
    {
        $this->action(PinDeviceToRelease::class)($device, $releaseId);
    }

    /**
     * Track the fleet's release again.
     *
     * @param  int|string|array<string, mixed>  $device
     */
    public function unpin(int|string|array $device): void
    {
        $this->action(PinDeviceToRelease::class)($device, null);
    }

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function deactivate(int|string|array $device): void
    {
        $this->action(DeactivateDevice::class)($device);
    }

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function delete(int|string|array $device): void
    {
        $this->action(DeleteDevice::class)($device);
    }

    // ------------------------------------------------------- supervisor calls

    /**
     * @return array<array-key, mixed>
     */
    public function reboot(string $uuid, bool $force = false): array
    {
        return $this->action(RebootDevice::class)($uuid, $force);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function blink(string $uuid): array
    {
        return $this->action(BlinkDevice::class)($uuid);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function purge(string $uuid, int $appId): array
    {
        return $this->action(PurgeDeviceData::class)($uuid, $appId);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function restartServices(string $uuid, int $appId): array
    {
        return $this->action(RestartFleetServices::class)($uuid, $appId);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function restartService(string $uuid, int $appId, string $serviceName): array
    {
        return $this->action(RestartService::class)($uuid, $appId, $serviceName);
    }

    /**
     * Per-app and per-service state from the device's own supervisor, which is
     * where a failed update surfaces. One call per device, online devices only.
     */
    public function state(string $uuid): DeviceState
    {
        return $this->action(GetDeviceState::class)($uuid);
    }

    /**
     * Whether the device has finished applying its target state.
     */
    public function supervisorStatus(string $uuid): SupervisorStatus
    {
        return $this->action(GetSupervisorStatus::class)($uuid);
    }

    public function hasSettled(string $uuid): bool
    {
        return $this->supervisorStatus($uuid)->hasSettled();
    }
}
