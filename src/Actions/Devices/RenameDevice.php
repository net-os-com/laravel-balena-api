<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Devices;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Change a device's name.
 */
final readonly class RenameDevice
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int|string|array<string, mixed>  $device  id, UUID, or key attributes
     */
    public function __invoke(int|string|array $device, string $name): void
    {
        $this->client->pine(
            'PATCH',
            ResourceKey::make($device)->appliedTo('device'),
            body: ['device_name' => $name],
        );
    }
}
