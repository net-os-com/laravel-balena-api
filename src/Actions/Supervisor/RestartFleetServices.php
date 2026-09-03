<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Http\BalenaClient;

/**
 * Restart every service a fleet runs on one device.
 */
final readonly class RestartFleetServices
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @return array<array-key, mixed>
     */
    public function __invoke(string $uuid, int $appId): array
    {
        return $this->client->call('POST', "supervisor/v2/applications/{$appId}/restart", [
            'uuid' => $uuid,
        ]);
    }
}
