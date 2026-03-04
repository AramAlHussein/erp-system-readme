# ADR-001: BlackBox Audit via Event-Driven Architecture

## Status

Accepted

## Context

Every warehouse operation needs forensic-level logging.
Direct service calls inside business logic would create
tight coupling between audit concerns and business concerns.

## Decision

BlackBox logging is triggered via Laravel Events (WarehouseOperationEvent)
and handled by a dedicated Listener; completely decoupled from business logic.

## Consequences

### Positive

- Business logic remains clean and unaware of audit implementation
- Audit strategy can change without touching any Service class
- Follows Open/Closed Principle

### Negative

- Slightly harder to trace the logging flow without knowing the Event system
