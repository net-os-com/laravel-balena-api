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

## Device status and health

`overall_status` — the value the dashboard shows — is returned **only when a
query selects it by name**. A plain `get()` leaves it `null`, which reads like
"no status" rather than "you didn't ask". `withStatus()` selects it along with
the rest of the health fields:

```php
$devices = Balena::devices()->withStatus()->get();

$device->overallStatus;       // 'Operational' | 'Reduced Functionality' | 'Disconnected'
                              // | 'Configuring' | 'Updating' | 'Post Provisioning'
                              // | 'Inactive' | 'Frozen'
$device->isOperational();     // bool, or null if overall_status wasn't selected
$device->apiHeartbeatState;
$device->isConnectedToVpn;
$device->downloadProgress;
$device->cpuUsage; $device->cpuTemp;
$device->memoryUsage; $device->memoryTotal;
$device->storageUsage; $device->storageTotal;
$device->isUndervolted;
```

The field list is public, so it composes with the other scopes in either order:

```php
Balena::devices()->withStatus()->where('is_online', false)->get();
Balena::devices()->inFleet('org/fleet')->select(...DeviceResource::STATUS_FIELDS)->get();
```

`statusInFleet()` adds the running and target releases, so a pending update is
visible in the same request:

```php
foreach (Balena::devices()->statusInFleet('myorg/myfleet')->get() as $device) {
    $pending = $device->hasPendingUpdate();   // null when a release was not expanded
}
```

### The whole device at once

`withStatus()` is deliberately narrow — it carries what changes minute to
minute. When you want the rest as well (host OS, OS variant, supervisor
version, IP and MAC, public address, note, the running, target and pinned
releases, the device's tags and its variables), use `withDetails()`:

```php
$device = Balena::devices()->detailsByUuid($uuid);

$device->osVersion;              // 'balenaOS 2.115.18+rev1'
$device->osVariant;              // 'prod'
$device->supervisorVersion;      // '14.11.19'
$device->ipAddress;              // '192.168.1.44'
$device->macAddress;
$device->publicAddress;
$device->note;

$device->runningRelease()?->rawVersion;
$device->targetRelease()?->rawVersion;
$device->pinnedRelease()?->commit;
$device->hasPendingUpdate();     // true when running !== target, null if unknown

$device->tags();                 // ['location' => 'amsterdam', 'role' => 'kiosk']
$device->tag('location');        // 'amsterdam'
```

Same thing per fleet, or composed with any filter:

```php
Balena::devices()->detailsInFleet('myorg/myfleet')->get();
Balena::devices()->withDetails()->where('is_online', false)->get();
```

Tags come back expanded on the device, so there is no second request. Writing
them still goes through the tag resource: `Balena::deviceTags()->set($id, …)`.

`withDetails()` is a heavier query than `withStatus()` — prefer the narrow one
for polling, and this one for a device detail page.

### Variables on the device

`withDetails()` brings the device's variables with it, and `withVariables()`
asks for them without the rest:

```php
$device = Balena::devices()->withVariables()->where('uuid', $uuid)->first();

$device->environmentVariables();   // ['API_URL' => 'https://…', 'LOG_LEVEL' => 'debug']
$device->environmentVariable('LOG_LEVEL');

$device->configVariables();        // ['BALENA_HOST_CONFIG_gpu_mem' => '128', …]
$device->configVariable('BALENA_HOST_CONFIG_gpu_mem');

$device->serviceVariables();       // ['mqtt' => ['BROKER_URL' => 'tcp://…'], 'api' => […]]
$device->serviceVariable('mqtt', 'BROKER_URL');
```

Service variables are not a relation on the device: balena hangs them off a
**service install**, the row pairing a device with one of its fleet's
services. The expand goes through there and pulls `installs__service` in
alongside the variables, which is where the service name to key them by comes
from. Expanding `device_service_environment_variable` straight off a device is
not a navigation balena has, and it answers an unknown one with a 401 and an
empty body rather than a 400 — so that mistake reads like a bad token.

Two things these do **not** cover. They are the variables set *on the device*,
so a value the device inherits from its fleet is absent here — it lives on the
fleet, and the device only overrides it. And an unexpanded relation and a
device with no variables both read as an empty array, because Pine omits a
relation it was not asked for rather than returning it empty.

Writing still goes through the variable resources below.

## Per-device errors

balena has no error field. The resource API carries no error text, and
`service_install` has no status, so **per-service state is not queryable in
bulk** — it comes from the device's own supervisor, one call per device, and
only while the device is online.

```php
$state = Balena::devices()->state($uuid);   // DeviceState

$state->updateStatus();     // 'Done' | 'Downloading' | 'Downloaded'
                            // | 'Applying Changes' | 'Rejected' | 'Aborted'
$state->isFailing();        // true for Rejected or Aborted
$state->isUpdating();
$state->stalledServices();  // services neither running nor mid-transition
```

`Rejected` is the clearest failure: a required service in the target release
doesn't meet the requirements to run. `Aborted` means the supervisor gave up.
Comparisons are case-insensitive, because balena's docs give these names
capitalised while the supervisor returns them lower-cased.

Per service:

```php
foreach ($state->services() as $service) {
    $service->name;
    $service->status;            // 'Running', 'Downloading', 'Installing', ...
    $service->downloadProgress;
    $service->isRunning();
    $service->isSettling();      // mid-transition rather than stuck
}
```

To check whether a device has finished applying its target state:

```php
Balena::devices()->hasSettled($uuid);              // bool
Balena::devices()->supervisorStatus($uuid)->appState;   // 'applying' | 'applied'
```

That endpoint also returns a top-level `status` field which this package
deliberately does not expose: balena documents that it reports the HTTP
response and "does not indicate the status of the device itself". It is still
reachable on `->raw` if you need it.

`state()` needs supervisor 7.12 or later, and 17.7.0 for `updateStatus`.
`hasSettled()` needs 9.7 or later.

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
// https://api.balena-cloud.com/v7/device?$filter=belongs_to__application/any(a:a/slug%20eq%20'myorg%2Fmyfleet')
```

Query URLs preserve OData separators, including commas in `$select` and nested
options in `$expand`. Spaces and literal contents are URL-encoded; apostrophes
inside strings are doubled using OData escaping. Pass original values to the
builder, and unencoded OData expressions to `whereRaw()` or the client's query
options, rather than pre-encoding them.

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
Balena::releases()->latestSuccessful('myorg/myfleet');  // newest built + not invalidated
Balena::releases()->latestFinal('myorg/myfleet');       // the same, excluding pre-releases

Balena::fleets()->trackedRelease('myorg/myfleet');      // what tracking devices will run
```

`trackedRelease()` reads the fleet's own `should_be_running__release` and is the
precise answer to "what will devices get"; the `latest*` helpers are a search
over the release list.

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

These are the write path, and the read path for one owner at a time. To read a
device's variables without a request per kind, expand them onto the device
itself — see [Variables on the device](#variables-on-the-device).

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
