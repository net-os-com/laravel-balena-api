<?php

declare(strict_types=1);

namespace NetOs\Balena;

use NetOs\Balena\Data\User;
use NetOs\Balena\Enums\TagKind;
use NetOs\Balena\Enums\VariableKind;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Query\PineQuery;
use NetOs\Balena\Resources\DeviceResource;
use NetOs\Balena\Resources\FleetResource;
use NetOs\Balena\Resources\ReleaseResource;
use NetOs\Balena\Resources\TagResource;
use NetOs\Balena\Resources\VariableResource;
use NetOs\Balena\Support\ActionFactory;

/**
 * Entry point to the balena API.
 *
 * Resources are built per call rather than shared, so a client narrowed with
 * withToken() or withBaseUrl() reaches everything reached through it.
 */
class Balena
{
    /**
     * Final so that withToken() and withBaseUrl() can safely build a new
     * instance of the called class.
     */
    final public function __construct(
        private readonly BalenaClient $client,
        private readonly ActionFactory $actions,
    ) {}

    /**
     * Talk to balena as somebody else, e.g. with a session token belonging to
     * the logged-in user.
     */
    public function withToken(string $token): static
    {
        return new static($this->client->withToken($token), $this->actions);
    }

    /**
     * Talk to a different installation, e.g. self-hosted openBalena.
     */
    public function withBaseUrl(string $baseUrl): static
    {
        return new static($this->client->withBaseUrl($baseUrl), $this->actions);
    }

    public function client(): BalenaClient
    {
        return $this->client;
    }

    // ------------------------------------------------------------- resources

    public function devices(): DeviceResource
    {
        return new DeviceResource($this->client, $this->actions);
    }

    public function fleets(): FleetResource
    {
        return new FleetResource($this->client, $this->actions);
    }

    public function releases(): ReleaseResource
    {
        return new ReleaseResource($this->client, $this->actions);
    }

    public function variables(VariableKind $kind): VariableResource
    {
        return new VariableResource($this->client, $this->actions, $kind);
    }

    public function deviceEnvVars(): VariableResource
    {
        return $this->variables(VariableKind::DeviceEnvironment);
    }

    public function deviceConfigVars(): VariableResource
    {
        return $this->variables(VariableKind::DeviceConfig);
    }

    public function deviceServiceVars(): VariableResource
    {
        return $this->variables(VariableKind::DeviceService);
    }

    public function fleetEnvVars(): VariableResource
    {
        return $this->variables(VariableKind::FleetEnvironment);
    }

    public function fleetConfigVars(): VariableResource
    {
        return $this->variables(VariableKind::FleetConfig);
    }

    public function fleetServiceVars(): VariableResource
    {
        return $this->variables(VariableKind::FleetService);
    }

    public function tags(TagKind $kind): TagResource
    {
        return new TagResource($this->client, $this->actions, $kind);
    }

    public function deviceTags(): TagResource
    {
        return $this->tags(TagKind::Device);
    }

    public function fleetTags(): TagResource
    {
        return $this->tags(TagKind::Fleet);
    }

    public function releaseTags(): TagResource
    {
        return $this->tags(TagKind::Release);
    }

    // ----------------------------------------------------------------- other

    /**
     * The identity behind the current token.
     */
    public function whoami(): User
    {
        return User::fromArray($this->client->call('GET', 'user/v1/whoami'));
    }

    /**
     * Query any Pine resource this package does not wrap yet — api_key, team,
     * organization, ssh_key and the rest all work through here.
     *
     * @param  class-string|null  $dto  Omit to get raw arrays back.
     */
    public function query(string $resource, ?string $dto = null): PineQuery
    {
        return new PineQuery($this->client, $resource, $dto);
    }
}
