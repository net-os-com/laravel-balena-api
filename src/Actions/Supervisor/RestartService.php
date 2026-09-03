<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Http\BalenaClient;

/**
 * Restart a single named service on one device.
 */
final readonly class RestartService
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @return array<array-key, mixed>
     */
    public function __invoke(string $uuid, int $appId, string $serviceName): array
    {
        return $this->client->call('POST', "supervisor/v2/applications/{$appId}/restart-service", [
            'uuid' => $uuid,
            'data' => ['serviceName' => $serviceName],
        ]);
    }
}
