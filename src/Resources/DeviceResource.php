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
use NetOs\Balena\Actions\Supervisor\PurgeDeviceData;
use NetOs\Balena\Actions\Supervisor\RebootDevice;
use NetOs\Balena\Actions\Supervisor\RestartFleetServices;
use NetOs\Balena\Actions\Supervisor\RestartService;
use NetOs\Balena\Data\Device;
use NetOs\Balena\Query\PineQuery;

/**
 * @mixin PineQuery
 */
class DeviceResource extends Resource
{
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

    public function online(): PineQuery
    {
        return $this->query()->where('is_online', true);
    }

    public function offline(): PineQuery
    {
        return $this->query()->where('is_online', false);
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
}
