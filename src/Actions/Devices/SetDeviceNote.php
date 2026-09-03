<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Devices;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Set or clear a device's note.
 */
final readonly class SetDeviceNote
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int|string|array<string, mixed>  $device
     */
    public function __invoke(int|string|array $device, ?string $note): void
    {
        $this->client->pine(
            'PATCH',
            ResourceKey::make($device)->appliedTo('device'),
            body: ['note' => $note],
        );
    }
}
