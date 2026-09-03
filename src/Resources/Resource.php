<?php

declare(strict_types=1);

namespace NetOs\Balena\Resources;

use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;
use NetOs\Balena\Support\ActionFactory;

/**
 * Base for the typed resource accessors.
 *
 * A resource contributes three things and nothing more: the Pine resource
 * name, the DTO to hydrate into, and domain-shaped scopes. Everything else is
 * the query builder's job, and unknown method calls are forwarded to a fresh
 * one so `Balena::devices()->where(...)` reads naturally.
 */
abstract class Resource
{
    public function __construct(
        protected readonly BalenaClient $client,
        protected readonly ActionFactory $actions,
    ) {}

    /**
     * The Pine resource name, which is not always the domain name: fleets are
     * stored as "application".
     */
    abstract public function resourceName(): string;

    /**
     * @return class-string|null
     */
    abstract public function dto(): ?string;

    public function query(): PineQuery
    {
        return new PineQuery($this->client, $this->resourceName(), $this->dto());
    }

    /**
     * Resolve an Action bound to this resource's client, so a runtime token
     * override survives into the write.
     *
     * @template TAction of object
     *
     * @param  class-string<TAction>  $action
     * @return TAction
     */
    protected function action(string $action): object
    {
        return $this->actions->make($action, $this->client);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->query()->{$method}(...$arguments);
    }
}
