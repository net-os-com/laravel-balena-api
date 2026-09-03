<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Tags;

use NetOs\Balena\Enums\TagKind;
use NetOs\Balena\Exceptions\ResourceNotFoundException;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Create or update a tag on a device, fleet or release.
 *
 * Tags have a composite natural key — owner plus tag_key — which balena
 * addresses directly, so this needs no lookup: PATCH the composite key, and
 * fall back to POST when the tag does not exist yet.
 */
final readonly class SetTag
{
    public function __construct(private BalenaClient $client) {}

    public function __invoke(TagKind $kind, int $ownerId, string $key, string $value): void
    {
        $resourceKey = ResourceKey::fromAttributes([
            $kind->ownerField() => $ownerId,
            'tag_key' => $key,
        ]);

        try {
            $this->client->pine(
                'PATCH',
                $resourceKey->appliedTo($kind->resource()),
                body: ['value' => $value],
            );
        } catch (ResourceNotFoundException) {
            $this->client->pine('POST', $kind->resource(), body: [
                $kind->ownerField() => $ownerId,
                'tag_key' => $key,
                'value' => $value,
            ]);
        }
    }
}
