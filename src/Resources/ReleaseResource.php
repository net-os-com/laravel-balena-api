<?php

declare(strict_types=1);

namespace NetOs\Balena\Resources;

use NetOs\Balena\Data\Release;
use NetOs\Balena\Query\PineQuery;

/**
 * @mixin PineQuery
 */
class ReleaseResource extends Resource
{
    public function resourceName(): string
    {
        return 'release';
    }

    public function dto(): ?string
    {
        return Release::class;
    }

    public function byId(int $id): ?Release
    {
        /** @var Release|null */
        return $this->query()->find($id);
    }

    public function byCommit(string $commit): ?Release
    {
        /** @var Release|null */
        return $this->query()->where('commit', $commit)->first();
    }

    /**
     * Releases belonging to a fleet, addressed by slug or numeric id.
     */
    public function forFleet(int|string $fleet): PineQuery
    {
        if (is_int($fleet) || ctype_digit($fleet)) {
            return $this->query()->where('belongs_to__application', (int) $fleet);
        }

        return $this->query()->whereRelation(
            'belongs_to__application',
            fn (PineQuery $query): PineQuery => $query->where('slug', $fleet),
        );
    }

    /**
     * The newest successful, finalised release for a fleet — what a device
     * tracking that fleet would run.
     */
    public function latestFinal(int|string $fleet): ?Release
    {
        /** @var Release|null */
        return $this->forFleet($fleet)
            ->where('status', 'success')
            ->where('is_final', true)
            ->where('is_invalidated', false)
            ->orderByDesc('created_at')
            ->first();
    }
}
