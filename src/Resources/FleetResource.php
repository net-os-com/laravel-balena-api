<?php

declare(strict_types=1);

namespace NetOs\Balena\Resources;

use NetOs\Balena\Data\Fleet;
use NetOs\Balena\Data\Release;
use NetOs\Balena\Query\PineQuery;

/**
 * Fleets. The underlying Pine resource is named "application".
 *
 * @mixin PineQuery
 */
class FleetResource extends Resource
{
    public function resourceName(): string
    {
        return 'application';
    }

    public function dto(): ?string
    {
        return Fleet::class;
    }

    public function byId(int $id): ?Fleet
    {
        /** @var Fleet|null */
        return $this->query()->find($id);
    }

    /**
     * Look a fleet up by its slug, e.g. "myorg/myfleet".
     */
    public function bySlug(string $slug): ?Fleet
    {
        /** @var Fleet|null */
        return $this->query()->where('slug', $slug)->first();
    }

    public function byName(string $name): ?Fleet
    {
        /** @var Fleet|null */
        return $this->query()->where('app_name', $name)->first();
    }

    /**
     * The release this fleet hands to devices that track it.
     */
    public function trackedRelease(int|string $fleet): ?Release
    {
        $query = is_int($fleet) || ctype_digit($fleet)
            ? $this->query()->where('id', (int) $fleet)
            : $this->query()->where('slug', $fleet);

        $found = $query->select('id')->expand('should_be_running__release')->first();

        return $found instanceof Fleet
            ? $found->trackedRelease()
            : null;
    }

    /**
     * Fleets belonging to one organization.
     */
    public function inOrganization(int $organizationId): PineQuery
    {
        return $this->query()->where('organization', $organizationId);
    }
}
