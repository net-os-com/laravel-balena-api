<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Http\BalenaClient;

/**
 * Reboot a device through the supervisor proxy.
 *
 * Supervisor endpoints are not versioned resources: they sit at the host root
 * and take the target device in the body rather than the path.
 */
final readonly class RebootDevice
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @param  bool  $force  Reboot even while an update is in progress.
     * @return array<array-key, mixed>
     */
    public function __invoke(string $uuid, bool $force = false): array
    {
        return $this->client->call('POST', 'supervisor/v1/reboot', [
            'uuid' => $uuid,
            'data' => ['force' => $force],
        ]);
    }
}
