<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Http\BalenaClient;

/**
 * Clear a device's persistent application data.
 *
 * Destructive and not recoverable: the device's /data volume is emptied.
 */
final readonly class PurgeDeviceData
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  int  $appId  The fleet whose data should be purged on that device.
     * @return array<array-key, mixed>
     */
    public function __invoke(string $uuid, int $appId): array
    {
        return $this->client->call('POST', 'supervisor/v1/purge', [
            'uuid' => $uuid,
            'data' => ['appId' => $appId],
        ]);
    }
}
