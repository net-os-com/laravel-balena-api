<?php

declare(strict_types=1);

namespace NetOs\Balena\Support;

use Illuminate\Contracts\Container\Container;
use NetOs\Balena\Http\BalenaClient;

/**
 * Resolves an Action bound to a specific client.
 *
 * Actions are ordinary container-resolvable classes, so `app(RebootDevice::class)`
 * works and gets the configured client. This factory exists for the other case:
 * when a caller has narrowed the client with withToken() or withBaseUrl(), the
 * Action has to receive that instance rather than the container's singleton.
 *
 * It also keeps resource classes from taking one constructor argument per
 * Action they expose.
 */
final readonly class ActionFactory
{
    public function __construct(private Container $container) {}

    /**
     * @template TAction of object
     *
     * @param  class-string<TAction>  $action
     * @return TAction
     */
    public function make(string $action, BalenaClient $client): object
    {
        return $this->container->make($action, ['client' => $client]);
    }
}
