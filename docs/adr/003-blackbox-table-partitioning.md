# ADR-003: Range Partitioning on warehouse_black_boxes Table

## Status

Accepted

## Context

The BlackBox table will accumulate thousands of records monthly.
Queries filtered by date or year will slow down significantly over time
without a partitioning strategy.

## Decision

Partition the table by RANGE on performed_year (a stored generated column).
Each year gets its own partition. A p_future partition handles overflow.

## Consequences

### Positive

- Year-scoped queries touch only the relevant partition
- Scales cleanly as data grows year over year
- Partition pruning handled at DB engine level

### Negative

- Requires composite primary key (id + performed_year)
- Partitioning not supported on all environments (handled with graceful fallback)
