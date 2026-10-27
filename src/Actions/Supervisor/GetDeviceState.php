<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Data\DeviceState;
use NetOs\Balena\Http\BalenaClient;

/**
 * Read the per-app and per-service state a device's supervisor reports.
 *
 * This is the only route to per-service status: the resource API has no error
 * field, and service_install carries no status, so nothing here is available
 * in bulk. One call per device, and only while the device is online.
 *
 * The proxy defaults to POST, so reaching the supervisor's GET endpoint means
 * an HTTP POST carrying "method": "GET" in the body.
 *
 * Requires supervisor 7.12 or later, and 17.7.0 for updateStatus.
 */
final readonly class GetDeviceState
{
    public function __construct(private BalenaClient $client) {}

    public function __invoke(string $uuid): DeviceState
    {
        return DeviceState::fromArray($this->client->call('POST', 'supervisor/v2/applications/state', [
            'uuid' => $uuid,
            'method' => 'GET',
        ]));
    }
}
