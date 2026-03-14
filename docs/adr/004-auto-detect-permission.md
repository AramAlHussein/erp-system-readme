# ADR-004: Zero-Config Permission Enforcement via Route Name Auto-Detection

## Status

Accepted

## Context

In a modular ERP system with hundreds of named routes across multiple modules,
manually declaring permission middleware on every route creates two problems:

1. **Developer overhead** — every new route requires a conscious permission declaration.
2. **Silent security gaps** — a developer adding a route without attaching middleware
   leaves it unprotected with no warning, no error, and no visibility.

The standard approach (e.g., `->middleware('can:permission.name')` per route)
scales poorly and relies entirely on developer discipline.

## Decision

Implement a single global middleware (`AutoDetectPermission`) that:

1. Reads the current route name on every request
2. Checks if that route name exists in a cached permission list
3. If it does — enforces the permission automatically via `Gate::check()`
4. If it doesn't — passes the request through (route is considered public or unconfigured)

Permissions are created from routes via `php artisan permissions:sync`,
which scans all named routes and creates matching `Permission` records.

This inverts the model: instead of attaching security to routes,
security is derived from routes automatically.

## Consequences

### Positive

- **Zero-config enforcement** — new routes are protected the moment their permission is synced
- **No per-route middleware declarations** — protection is consistent and centralized
- **Auditable** — the permission table is the single source of truth for what is protected
- **Dry-run safe** — `permissions:sync --dry` previews changes before committing

### Negative

- Routes without a matching permission record are silently unprotected
- Renaming a route without re-running `permissions:sync` causes silent permission loss
- The enforcement relies on route naming conventions being consistent across modules

## Compliance

Both risks are mitigated by making `permissions:sync` part of the deployment checklist
and treating route names as a stable contract — not an implementation detail.