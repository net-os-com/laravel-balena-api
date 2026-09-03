<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Devices;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Permanently remove a device from balena.
 *
 * Requires an explicit key. A filtered DELETE against the device collection
 * would remove every match, and balena has no safeguard against that.
 */
final readonly class DeleteDevice
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function __invoke(int|string|array $device): void
    {
        $this->client->pine(
            'DELETE',
            ResourceKey::make($device)->appliedTo('device'),
        );
    }
}
