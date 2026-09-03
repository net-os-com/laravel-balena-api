<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Devices;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Move a device into a different fleet.
 */
final readonly class MoveDeviceToFleet
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int|string|array<string, mixed>  $device
     * @param  int  $fleetId  The target fleet's numeric id, not its slug.
     */
    public function __invoke(int|string|array $device, int $fleetId): void
    {
        $this->client->pine(
            'PATCH',
            ResourceKey::make($device)->appliedTo('device'),
            body: ['belongs_to__application' => $fleetId],
        );
    }
}
