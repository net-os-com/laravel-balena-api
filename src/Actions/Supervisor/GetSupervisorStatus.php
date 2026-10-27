<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Data\SupervisorStatus;
use NetOs\Balena\Http\BalenaClient;

/**
 * Read whether a device has settled on its target state.
 *
 * Requires supervisor 9.7 or later.
 */
final readonly class GetSupervisorStatus
{
    public function __construct(private BalenaClient $client) {}

    public function __invoke(string $uuid): SupervisorStatus
    {
        return SupervisorStatus::fromArray($this->client->call('POST', 'supervisor/v2/state/status', [
            'uuid' => $uuid,
            'method' => 'GET',
        ]));
    }
}
