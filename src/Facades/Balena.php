<?php

declare(strict_types=1);

namespace NetOs\Balena\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \NetOs\Balena\Balena withToken(string $token)
 * @method static \NetOs\Balena\Balena withBaseUrl(string $baseUrl)
 * @method static \NetOs\Balena\Http\BalenaClient client()
 * @method static \NetOs\Balena\Resources\DeviceResource devices()
 * @method static \NetOs\Balena\Resources\FleetResource fleets()
 * @method static \NetOs\Balena\Resources\ReleaseResource releases()
 * @method static \NetOs\Balena\Resources\VariableResource variables(\NetOs\Balena\Enums\VariableKind $kind)
 * @method static \NetOs\Balena\Resources\VariableResource deviceEnvVars()
 * @method static \NetOs\Balena\Resources\VariableResource deviceConfigVars()
 * @method static \NetOs\Balena\Resources\VariableResource deviceServiceVars()
 * @method static \NetOs\Balena\Resources\VariableResource fleetEnvVars()
 * @method static \NetOs\Balena\Resources\VariableResource fleetConfigVars()
 * @method static \NetOs\Balena\Resources\VariableResource fleetServiceVars()
 * @method static \NetOs\Balena\Resources\TagResource tags(\NetOs\Balena\Enums\TagKind $kind)
 * @method static \NetOs\Balena\Resources\TagResource deviceTags()
 * @method static \NetOs\Balena\Resources\TagResource fleetTags()
 * @method static \NetOs\Balena\Resources\TagResource releaseTags()
 * @method static \NetOs\Balena\Data\User whoami()
 * @method static \NetOs\Balena\Query\PineQuery query(string $resource, ?string $dto = null)
 *
 * @see \NetOs\Balena\Balena
 */
class Balena extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NetOs\Balena\Balena::class;
    }
}
