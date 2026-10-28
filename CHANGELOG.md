# Changelog

All notable changes to `:package_name` will be documented in this file.

## v2.1.0 - 2026-10-28

### Added

- `Balena::devices()->withVariables()` expands the three variable relations balena hangs off a device — environment, configuration and per-service — so they arrive with the device instead of costing a request each. `withDetails()` and `detailsByUuid()` now carry them too.
- `Device::environmentVariables()`, `configVariables()` and `serviceVariables()`, plus `environmentVariable()`, `configVariable()` and `serviceVariable()` for single lookups. Service variables are keyed by service name: the expand pulls the service in alongside them, because balena identifies a device service variable by device, service and name but ships the service link as a bare id until asked otherwise.

These are the variables set on the device itself. A value inherited from the fleet lives on the fleet and is not repeated here, and an unexpanded relation reads the same as a device with no variables, because Pine omits a relation it was not asked for.

### Fixed

- `Cast::relationId()` understood a bare id and an expanded row, but not the `{"__id": N}` shape balena returns for an unexpanded to-one link. `Fleet::$deviceTypeId`, `Fleet::$organizationId`, `Release::$fleetId` and both `ServiceInstall` ids were null in practice because of it.

### Note

`detailsInFleet()` is now considerably heavier: every tag and every variable of every device comes back with it. `statusInFleet()` remains the query to poll.

## v2.0.0 - 2026-10-27

### Breaking

- `Device::$overallProgress` and `Device::$modifiedAt` were removed. Neither was selected by any scope; read `$device->raw['overall_progress']` or `$device->raw['modified_at']` if you still need them.
- `Release::$isFinal` became the method `Release::isFinal()`.

### Added

**Device status and health.** `overall_status` — the value the dashboard shows — is returned by balena only when a query names it, so a plain `get()` left it null. `withStatus()` and `DeviceResource::STATUS_FIELDS` select it alongside heartbeat, VPN, provisioning, download progress and the health metrics, and `statusInFleet()` does the same per fleet with the running and target release expanded. New: `Device::isOperational()`.

**Per-service state.** `Balena::devices()->state($uuid)` and `supervisorStatus($uuid)` read what a device's own supervisor reports, with the `DeviceState`, `AppState`, `ServiceState` and `SupervisorStatus` DTOs. The resource API has no error field and `service_install` carries no status, so this is the only route to per-service status: one proxied call per device, online devices only. `updateStatus()`, `isFailing()`, `stalledServices()` and `hasSettled()` cover the failure cases.

**The whole device in one request.** `withDetails()`, `detailsByUuid()` and `detailsInFleet()` add the host OS, OS variant, supervisor version, IP, MAC, public address, note and activity fields, and expand the running, target and pinned releases plus the device's tags. New: `Device::tags()`, `Device::tag()` and `Device::hasPendingUpdate()`.

**Fleet and release.** `FleetResource::trackedRelease()` and `Fleet::trackedRelease()` for what a fleet actually hands its devices, `ReleaseResource::latestSuccessful()`, and `Release::$rawVersion`, `$semverPrerelease`, `isUsable()` and `isFinal()`.

### Fixed

- Query-string encoding: OData option names and expression punctuation now reach balena literally while values and spaces are encoded, and apostrophes inside string literals are doubled (#1, thanks @pcvanroy).
