# ADR-002: Service Container Resolution in HandlesWarehouseBlackBox Trait

## Status

Accepted

## Context

The HandlesWarehouseBlackBox trait is used across 10+ Service classes.
Constructor Injection would require adding WarehouseBlackBoxServiceInterface
to every constructor — repeated boilerplate with no architectural benefit.

## Decision

Resolve WarehouseBlackBoxServiceInterface via app() inside the Trait.

## Consequences

### Positive

- Consuming classes stay clean; one line to initialize
- DRY: dependency declared once, not in every constructor

### Negative

- Hidden dependency (not visible in constructor signature)
- Requires container binding during testing

## Compliance

Safe within this closed Enterprise ERP; container bindings are stable and controlled.
