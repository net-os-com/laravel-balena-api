<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Tags;

use NetOs\Balena\Enums\TagKind;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\ResourceKey;

/**
 * Remove a tag from a device, fleet or release.
 */
final readonly class DeleteTag
{
    public function __construct(private BalenaClient $client) {}

    public function __invoke(TagKind $kind, int $ownerId, string $key): void
    {
        $this->client->pine(
            'DELETE',
            ResourceKey::fromAttributes([
                $kind->ownerField() => $ownerId,
                'tag_key' => $key,
            ])->appliedTo($kind->resource()),
        );
    }
}
