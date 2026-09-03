<?php

declare(strict_types=1);

namespace NetOs\Balena\Resources;

use NetOs\Balena\Actions\Tags\DeleteTag;
use NetOs\Balena\Actions\Tags\SetTag;
use NetOs\Balena\Data\Tag;
use NetOs\Balena\Enums\TagKind;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;
use NetOs\Balena\Support\ActionFactory;

/**
 * Device, fleet and release tags, parameterised by TagKind the same way
 * VariableResource is parameterised by VariableKind.
 *
 * @mixin PineQuery
 */
class TagResource extends Resource
{
    public function __construct(
        BalenaClient $client,
        ActionFactory $actions,
        private readonly TagKind $kind,
    ) {
        parent::__construct($client, $actions);
    }

    public function resourceName(): string
    {
        return $this->kind->resource();
    }

    public function dto(): ?string
    {
        return Tag::class;
    }

    public function kind(): TagKind
    {
        return $this->kind;
    }

    public function forOwner(int $ownerId): PineQuery
    {
        return $this->query()->where($this->kind->ownerField(), $ownerId);
    }

    /**
     * Tags on one owner, keyed by tag key.
     *
     * @return array<string, string|null>
     */
    public function pairsFor(int $ownerId): array
    {
        $pairs = [];

        /** @var Tag $tag */
        foreach ($this->forOwner($ownerId)->select('tag_key', 'value')->get() as $tag) {
            if ($tag->key !== null) {
                $pairs[$tag->key] = $tag->value;
            }
        }

        return $pairs;
    }

    public function withKey(int $ownerId, string $key): ?Tag
    {
        /** @var Tag|null */
        return $this->forOwner($ownerId)->where('tag_key', $key)->first();
    }

    public function set(int $ownerId, string $key, string $value): void
    {
        $this->action(SetTag::class)($this->kind, $ownerId, $key, $value);
    }

    public function delete(int $ownerId, string $key): void
    {
        $this->action(DeleteTag::class)($this->kind, $ownerId, $key);
    }
}
