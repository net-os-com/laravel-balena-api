<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Devices;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Pin a device to a release, or unpin it so it tracks the fleet again.
 */
final readonly class PinDeviceToRelease
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int|string|array<string, mixed>  $device
     * @param  int|null  $releaseId  Null unpins the device.
     */
    public function __invoke(int|string|array $device, ?int $releaseId): void
    {
        $this->client->pine(
            'PATCH',
            ResourceKey::make($device)->appliedTo('device'),
            body: ['is_pinned_on__release' => $releaseId],
        );
    }
}
