<?php

declare(strict_types=1);

namespace NetOs\Balena\Actions\Variables;

use NetOs\Balena\Enums\VariableKind;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;
use NetOs\Balena\Query\ResourceKey;

/**
 * Create or update a variable on its owning resource.
 *
 * balena has no upsert, so this looks the variable up by owner and name first
 * and then either patches the existing row by id or posts a new one. The
 * lookup is what keeps the write addressed by key rather than by filter.
 */
final readonly class SetVariable
{
    public function __construct(private BalenaClient $client) {}

    public function __invoke(VariableKind $kind, int $ownerId, string $name, string $value): void
    {
        $existing = (new PineQuery($this->client, $kind->resource()))
            ->select('id')
            ->where($kind->ownerField(), $ownerId)
            ->where('name', $name)
            ->take(1)
            ->raw();

        $id = $existing[0]['id'] ?? null;

        if (is_numeric($id)) {
            $this->client->pine(
                'PATCH',
                ResourceKey::fromId((int) $id)->appliedTo($kind->resource()),
                body: ['value' => $value],
            );

            return;
        }

        $this->client->pine('POST', $kind->resource(), body: [
            $kind->ownerField() => $ownerId,
            'name' => $name,
            'value' => $value,
        ]);
    }
}
