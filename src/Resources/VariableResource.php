<?php

declare(strict_types=1);

namespace NetOs\Balena\Resources;

use NetOs\Balena\Actions\Variables\DeleteVariable;
use NetOs\Balena\Actions\Variables\SetVariable;
use NetOs\Balena\Data\Variable;
use NetOs\Balena\Enums\VariableKind;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;
use NetOs\Balena\Support\ActionFactory;

/**
 * Environment, config and service variables.
 *
 * balena exposes six of these resources; they differ only in endpoint and in
 * the field naming their owner, so the difference is carried by VariableKind
 * rather than by six near-identical classes.
 *
 * @mixin PineQuery
 */
class VariableResource extends Resource
{
    public function __construct(
        BalenaClient $client,
        ActionFactory $actions,
        private readonly VariableKind $kind,
    ) {
        parent::__construct($client, $actions);
    }

    public function resourceName(): string
    {
        return $this->kind->resource();
    }

    public function dto(): ?string
    {
        return Variable::class;
    }

    public function kind(): VariableKind
    {
        return $this->kind;
    }

    /**
     * Every variable on one owning resource.
     *
     * @param  int  $ownerId  A device, fleet, service or service-install id,
     *                        depending on the kind.
     */
    public function forOwner(int $ownerId): PineQuery
    {
        return $this->query()->where($this->kind->ownerField(), $ownerId);
    }

    /**
     * Variables on one owner, keyed by variable name.
     *
     * @return array<string, string|null>
     */
    public function pairsFor(int $ownerId): array
    {
        $pairs = [];

        /** @var Variable $variable */
        foreach ($this->forOwner($ownerId)->select('name', 'value')->get() as $variable) {
            if ($variable->name !== null) {
                $pairs[$variable->name] = $variable->value;
            }
        }

        return $pairs;
    }

    public function named(int $ownerId, string $name): ?Variable
    {
        /** @var Variable|null */
        return $this->forOwner($ownerId)->where('name', $name)->first();
    }

    /**
     * Create the variable, or update it when it already exists.
     */
    public function set(int $ownerId, string $name, string $value): void
    {
        $this->action(SetVariable::class)($this->kind, $ownerId, $name, $value);
    }

    /**
     * Returns false when there was no such variable to remove.
     */
    public function delete(int $ownerId, string $name): bool
    {
        return $this->action(DeleteVariable::class)($this->kind, $ownerId, $name);
    }
}
