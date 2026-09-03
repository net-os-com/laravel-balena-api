<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Supervisor;

use NetOs\Balena\Http\BalenaClient;

/**
 * Blink a device's LED so someone can find it on a shelf.
 */
final readonly class BlinkDevice
{
    public function __construct(private BalenaClient $client) {}

    /**
     * @return array<array-key, mixed>
     */
    public function __invoke(string $uuid): array
    {
        return $this->client->call('POST', 'supervisor/v1/blink', [
            'uuid' => $uuid,
        ]);
    }
}
