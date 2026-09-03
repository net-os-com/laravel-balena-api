<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Variables;

use NetOs\Balena\Enums\VariableKind;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;
use NetOs\Balena\Query\ResourceKey;

/**
 * Remove a variable from its owning resource.
 *
 * Resolves the variable's id first so the DELETE is addressed by key. Returns
 * false when there was nothing to delete.
 */
final readonly class DeleteVariable
{
    public function __construct(private BalenaClient $client) {}

    public function __invoke(VariableKind $kind, int $ownerId, string $name): bool
    {
        $existing = (new PineQuery($this->client, $kind->resource()))
            ->select('id')
            ->where($kind->ownerField(), $ownerId)
            ->where('name', $name)
            ->take(1)
            ->raw();

        $id = $existing[0]['id'] ?? null;

        if (! is_numeric($id)) {
            return false;
        }

        $this->client->pine(
            'DELETE',
            ResourceKey::fromId((int) $id)->appliedTo($kind->resource()),
        );

        return true;
    }
}
