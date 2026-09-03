# Laravel Balena API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/net-os/laravel-balena-api.svg?style=flat-square)](https://packagist.org/packages/net-os/laravel-balena-api)
[![Total Downloads](https://img.shields.io/packagist/dt/net-os/laravel-balena-api.svg?style=flat-square)](https://packagist.org/packages/net-os/laravel-balena-api)

A Laravel HTTP client wrapper for the [balenaCloud API](https://docs.balena.io/reference/api/overview/).

balena's API is an OData/Pine interface: you query it with `$select`, `$filter`,
`$expand` and friends rather than through per-endpoint methods. This package
puts a fluent builder over that, hydrates results into typed DTOs, and puts each
write behind its own single-purpose class.

```php
use NetOs\Balena\Facades\Balena;

$devices = Balena::devices()
    ->inFleet('myorg/myfleet')
    ->where('is_online', true)
    ->select('uuid', 'device_name', 'os_version')
    ->orderBy('device_name')
    ->get();

Balena::devices()->reboot('deadbeef...');
```

This package is a client wrapper only. It ships no migrations, models, Artisan
commands or caching.

## Installation

```bash
composer require net-os/laravel-balena-api
```

Publish the config file:

```bash
php artisan vendor:publish --tag="balena-config"
```

Then set your token. A balenaCloud session token or a named API key both work:

```dotenv
BALENA_API_TOKEN=your-token
```

The full config:

```php
return [
    'token'    => env('BALENA_API_TOKEN'),
    'base_url' => env('BALENA_API_URL', 'https://api.balena-cloud.com'),
    'version'  => env('BALENA_API_VERSION', 'v7'),
    'timeout'  => (int) env('BALENA_TIMEOUT', 15),
    'retry'    => ['times' => (int) env('BALENA_RETRY_TIMES', 3)],
];
```

`base_url` and `version` are separate because balena only versions part of its
surface: resource endpoints live under `/v7/`, while `/user/v1/whoami` and the
`/supervisor/*` proxy sit at the host root. Point `base_url` at your own
installation to use openBalena.

## Querying

Every resource accessor returns a fluent, immutable query builder. Unknown
methods fall through to it, so `Balena::devices()->where(...)` reads naturally.

```php
Balena::devices()->get();                       // Collection<Device>
Balena::devices()->byUuid('deadbeef...');       // ?Device
Balena::devices()->byId(1234);                  // ?Device
Balena::devices()->online()->count();           // int
Balena::devices()->inFleet('myorg/myfleet')->get();
Balena::devices()->inFleet(7)->get();           // by numeric fleet id
```

### Filtering

```php
use NetOs\Balena\Query\Filter\Operator;

Balena::devices()
    ->where('is_online', true)                          // is_online eq true
    ->where('id', '>=', 100)                            // id ge 100
    ->where('device_name', Operator::Contains, 'sensor')
    ->whereIn('status', ['idle', 'configuring'])
    ->whereNull('note')
    ->orWhere('is_web_accessible', true)
    ->get();
```

Group conditions by passing a closure. The builder is immutable, so **return the
query from the callback**:

```php
Balena::devices()
    ->where('is_online', true)
    ->where(fn ($q) => $q->where('status', 'idle')->orWhere('status', 'configuring'))
    ->get();
```

Filter across a relation with `whereRelation()`, which compiles to balena's
documented `rel/any(a:a/field eq '…')` form:

```php
Balena::devices()
    ->whereRelation('belongs_to__application', fn ($q) => $q->where('slug', 'myorg/myfleet'))
    ->get();
```

Anything the builder cannot express goes through `whereRaw()`.

### Selecting and expanding

```php
$device = Balena::devices()
    ->select('uuid', 'device_name')
    ->expand('belongs_to__application', fn ($q) => $q->select('app_name', 'slug'))
    ->first();

$device->fleet()?->slug;   // 'myorg/myfleet'
```

Because `$select` returns partial rows, **every DTO property is nullable**. The
source payload stays available on `->raw`, so expanded relations and any fields
this package does not map are never lost:

```php
$device->raw['cpu_temp'] ?? null;
```

### Paging

```php
foreach (Balena::devices()->lazy() as $device) { /* ... */ }

Balena::devices()->chunk(200, function ($devices) {
    // return false to stop early
});
```

### Inspecting a query

`toUrl()` compiles a request without sending it. balena's own docs warn that
nothing stops you making widespread, irreversible mistakes, so read a query back
before running a destructive version of it:

```php
Balena::devices()->inFleet('myorg/myfleet')->toUrl();
// https://api.balena-cloud.com/v7/device?$filter=belongs_to__application/any(a:a/slug eq 'myorg/myfleet')
```

## Writes

Writes are addressed by key — id, UUID, or a composite key — never by filter:

```php
Balena::devices()->rename('deadbeef...', 'sensor-01');
Balena::devices()->setNote('deadbeef...', 'in the north shed');
Balena::devices()->moveToFleet('deadbeef...', fleetId: 7);
Balena::devices()->pinToRelease('deadbeef...', releaseId: 42);
Balena::devices()->unpin('deadbeef...');
Balena::devices()->deactivate('deadbeef...');
Balena::devices()->delete('deadbeef...');
```

A filtered `PATCH` or `DELETE` against a collection rewrites **every** matching
row, so bulk writes have to be asked for by name and require a filter:

```php
Balena::devices()
    ->where('is_online', false)
    ->patchMany(['note' => 'unreachable']);
```

### Supervisor actions

These go through balena's supervisor proxy rather than the resource API:

```php
Balena::devices()->reboot('deadbeef...', force: true);
Balena::devices()->blink('deadbeef...');
Balena::devices()->purge('deadbeef...', appId: 7);
Balena::devices()->restartServices('deadbeef...', appId: 7);
Balena::devices()->restartService('deadbeef...', appId: 7, serviceName: 'api');
```

## Fleets and releases

```php
Balena::fleets()->bySlug('myorg/myfleet');
Balena::fleets()->inOrganization(3)->get();

Balena::releases()->forFleet('myorg/myfleet')->get();
Balena::releases()->byCommit('abc123...');
Balena::releases()->latestFinal('myorg/myfleet');
```

## Variables and tags

balena exposes six variable resources that differ only in their endpoint and in
the field naming their owner, so one resource class covers all of them:

```php
Balena::deviceEnvVars()->pairsFor($deviceId);          // ['KEY' => 'value', ...]
Balena::deviceEnvVars()->set($deviceId, 'KEY', 'value');   // creates or updates
Balena::deviceEnvVars()->named($deviceId, 'KEY');          // ?Variable
Balena::deviceEnvVars()->delete($deviceId, 'KEY');         // false if absent

Balena::fleetConfigVars()->set($fleetId, 'RESIN_HOST_CONFIG_gpu_mem', '16');
```

Available: `deviceEnvVars()`, `deviceConfigVars()`, `deviceServiceVars()`,
`fleetEnvVars()`, `fleetConfigVars()`, `fleetServiceVars()`. Note that device
service variables are owned by a **service install**, not a device, and fleet
service variables by a **service**.

Tags work the same way:

```php
Balena::deviceTags()->pairsFor($deviceId);
Balena::deviceTags()->set($deviceId, 'location', 'shed-north');
Balena::deviceTags()->withKey($deviceId, 'location');
Balena::deviceTags()->delete($deviceId, 'location');
```

Also `fleetTags()` and `releaseTags()`.

## Resources without a typed accessor

Teams, organizations, memberships, API keys, SSH keys and OS releases have no
dedicated class yet, but the query layer reaches every Pine resource:

```php
Balena::query('organization')->where('handle', 'myorg')->get();   // array rows
Balena::query('my_application')->get();
```

Pass a DTO class as the second argument to hydrate the results.

## Identity and runtime overrides

```php
Balena::whoami();                                  // User behind the current token

Balena::withToken($sessionToken)->devices()->get();
Balena::withBaseUrl('https://api.openbalena.local')->fleets()->get();
```

Overrides return a new instance and carry through to reads, writes and
supervisor calls made from them.

## Dependency injection

The facade is a convenience. Everything is container-resolvable, and each write
is its own injectable class:

```php
use NetOs\Balena\Actions\Supervisor\RebootDevice;
use NetOs\Balena\Balena;

public function __construct(
    private Balena $balena,
    private RebootDevice $reboot,
) {}

public function handle(): void
{
    ($this->reboot)('deadbeef...');
}
```

## Errors

Every failure throws a subclass of `NetOs\Balena\Exceptions\BalenaException`,
carrying the HTTP status, the decoded body and the request URL.

| Status | Exception |
| --- | --- |
| 401 | `AuthenticationException` |
| 403 | `AuthorizationException` |
| 404 | `ResourceNotFoundException` |
| 400, 422 | `ValidationException` |
| 429 | `RateLimitException` |
| 5xx | `ServerException` |

balena does not publish its rate limits and returns `Retry-After` instead, so
429 and 5xx responses are retried automatically, waiting exactly as long as that
header asks. Other 4xx responses are not retried. `RateLimitException::retryAfter()`
exposes the value when a request fails anyway.

## Testing against this package

The package uses Laravel's HTTP client, so consuming applications can fake
balena with `Http::fake()` as usual — no test helpers required from here.

## Verifying against a live token

Five details could not be confirmed from balena's public documentation. Each is
isolated to a single file, so correcting one is a small change:

| What | Where |
| --- | --- |
| The Pine response envelope (`{"d": [...]}` vs bare) | `Http/PineResponse.php` — currently accepts both |
| `$count` syntax (`/resource/$count` vs `?$count=true`) | `Http/BalenaClient::pineCount()` |
| Date literal format when filtering timestamps | `Query/Filter/ValueFormatter::formatDateTime()` |
| `contains()` vs `substringof()` for substring matching | `Query/Filter/Operator` |
| How the supervisor proxy reports an offline device | `Http/BalenaClient::exceptionFor()` |

The variable resource names in `Enums/VariableKind` come from balena's Pine
model rather than a documentation page, and are worth confirming before relying
on the less common ones.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Rick Bongers](https://github.com/net-os)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
