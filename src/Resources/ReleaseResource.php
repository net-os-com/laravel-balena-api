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
     * The newest release of a fleet that built successfully and has not been
     * invalidated.
     *
     * Filters only on documented fields. For what the fleet will actually hand
     * its devices, prefer FleetResource::trackedRelease(), which reads the
     * fleet's own should_be_running__release.
     */
    public function latestSuccessful(int|string $fleet): ?Release
    {
        /** @var Release|null */
        return $this->forFleet($fleet)
            ->where('status', 'success')
            ->where('is_invalidated', false)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * As latestSuccessful(), excluding pre-releases.
     */
    public function latestFinal(int|string $fleet): ?Release
    {
        /** @var Release|null */
        return $this->forFleet($fleet)
            ->where('status', 'success')
            ->where('is_invalidated', false)
            ->where(fn (PineQuery $query): PineQuery => $query
                ->whereNull('semver_prerelease')
                ->orWhere('semver_prerelease', ''))
            ->orderByDesc('created_at')
            ->first();
    }
}
