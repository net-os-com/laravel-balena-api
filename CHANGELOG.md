# Changelog

All notable changes to `:package_name` will be documented in this file.

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
