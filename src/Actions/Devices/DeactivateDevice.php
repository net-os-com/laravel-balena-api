<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Devices;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Deactivate a device, releasing it from its fleet without deleting it.
 */
final readonly class DeactivateDevice
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function __invoke(int|string|array $device): void
    {
        $this->client->pine(
            'PATCH',
            ResourceKey::make($device)->appliedTo('device'),
            body: ['is_active' => false],
        );
    }
}
