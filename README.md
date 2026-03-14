# Advanced ERP Core — A Modern Business Management Platform

**ERP Core** is a production-deployed, modular ERP system built from the ground up to serve as the operational backbone of a mid-size enterprise. Engineered with a focus on automation, data integrity, and long-term maintainability.

> Deployed in production for **150+ employees** across **8 warehouses**, managing **150+ structured materials** with **400–1,000+ inventory transactions monthly**.

> Prior to implementation: ~10% inventory inaccuracy, fragmented manual processes, no audit trail, verbal approvals. The system replaced all of it.

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2 |
| Framework | Laravel 10 |
| Architecture | HMVC Modular (`nwidart/laravel-modules`) |
| Authentication | Laravel Fortify — custom pipeline |
| Authorization | Custom RBAC (position-based, route-derived) |
| Localization | Arabic / English (`mcamara/laravel-localization`) |
| Database | MySQL with range partitioning (BlackBox) |

---

## 🎯 The "Why": Solving Real Business Problems

Standard software often fails to capture the complexity of real business logic. This system was built to address specific operational pain points:

- **Permission Chaos** — hundreds of users, dozens of roles, constant access changes. Solved with a position-based RBAC where permissions are derived automatically from routes.
- **Human Error in Stock** — concurrent users processing the same stock simultaneously. Solved with database-level row locking before any mutation.
- **Audit Gaps** — no visibility into what happened, when, and why. Solved with a forensic-grade BlackBox audit system on every warehouse operation.
- **Procurement Bottlenecks** — manual handoffs between PR, RFQ, PO, GRN, and Invoice stages. Solved with event-driven automation that creates downstream documents automatically on status transitions.

---

## 🏛️ Architectural Principles

- **HMVC Modular** — each domain is a fully isolated module with its own routes, controllers, services, repositories, models, and migrations.
- **Repository → Service → Controller** — strict layering with Interfaces at every boundary.
- **Position-Based RBAC** — permissions are assigned to organizational positions, not individuals. Users inherit access through their role automatically.
- **Zero-Config Permission Enforcement** — routes are the source of truth for permissions. No per-route middleware declarations.
- **Event-Driven Automation** — business workflows (procurement lifecycle, audit logging) are driven by Events and Observers, keeping business logic decoupled.
- **Concurrency-Aware** — race conditions in stock operations are prevented at the database level with explicit row locking.

---

## 📦 Core Modules

### Module Dependency Map

```
AccessControl  ←  defines roles and permissions
     ↑
   Auth         ←  custom Fortify pipeline (6 steps)
     ↑
   HR           ←  positions auto-create roles in AccessControl
     ↑
Warehouses      ←  stock management, concurrency control, BlackBox audit
     ↑
Purchases       ←  full procurement cycle, GRN writes back to Warehouses
```

---

### 1. Human Resources

- Hierarchical department structure with nested management
- Positions defined as Unique or General — manager roles protected from modification
- Employee creation auto-generates a structured ID (`DeptCode-PositionCode-SeqID`) and a system user account
- Position creation auto-creates a matching Role in AccessControl via Observer

### 2. Inventory & Warehouse Management

- Each warehouse linked to a department — access controlled at warehouse level
- Item management with tree view, table view, multiple units of measure, file attachments
- Stock tracked across three states: **On-Hand**, **Reserved**, **Incoming**
- Transaction lifecycle: `Draft → Processing → Completed` — stock is reserved before physical movement
- Race conditions prevented via `ConcurrencyControlService` (see Showcase)
- Every operation logged forensically in the BlackBox (see Showcase)
- Approved outbound transactions are mapped into **weekly consumption periods** and auto-locked — protects historical data and ensures reporting integrity

### 3. Procurement

Full cycle from request to payment:

```
PR → (Approval) → RFQ → RFQ Response → PO → GRN → Invoice → Matching
```

- PR approval auto-triggers RFQ or PO creation via Model Observer
- GRN supports partial deliveries and auto-updates stock
- Invoice Matching compares billed quantities against received quantities minus returns
- Supplier database with classifications, contacts, product catalogs, price history, currencies, and tax codes
- BlanketAgreements for framework contracts with pre-agreed pricing

### 4. Access Control

- Custom RBAC built on top of `spatie/laravel-permission` (storage only)
- Permissions derived from route names — `permissions:sync` keeps DB in sync with codebase
- `AutoDetectPermission` global middleware enforces access automatically on every named route
- Cache layer prevents repeated DB queries per request

### 5. Authentication

Custom Fortify pipeline with ordered security steps:

```
AttemptToAuthenticate → EnsureUserIsActive → CheckAccountExpiry
→ EnsureUserHasRole → EnsureUserHasPermission → PrepareAuthenticatedSession
```

---

## 🔬 Architecture Showcase

Selected components demonstrating key architectural decisions.

### BlackBox Audit System
Forensic-grade logging for every warehouse operation. Three-stage capture: `trying` → `success` / `failed`

- [HandlesWarehouseBlackBox](./showcase/BlackBox/HandlesWarehouseBlackBox.php) — Reusable Trait
- [WarehouseOperationEvent](./showcase/BlackBox/WarehouseOperationEvent.php) — Event structure
- [BlackBox Migration](./showcase/BlackBox/create_warehouse_black_boxes_table.php) — DB schema with range partitioning strategy

### Concurrency Control
Row-level locking strategy for safe concurrent stock operations. During early adoption, a single warehouse recorded **18+ outbound movements in one day** — making race condition prevention a hard requirement, not an optimization.

- [ConcurrencyControlServiceInterface](./showcase/ConcurrencyControl/ConcurrencyControlServiceInterface.php) — Contract definition
- [ConcurrencyControlService](./showcase/ConcurrencyControl/ConcurrencyControlService.php) — Implementation with `lockForUpdate`

### Permission Sync Engine
Route names are the source of truth for permissions. No manual mapping.

- [PermissionSyncService](./showcase/AccessControl/PermissionSyncService.php) — Derives and syncs permissions from active module routes
- [SyncPermissionsFromRoutes](./showcase/AccessControl/SyncPermissionsFromRoutes.php) — DevOps-friendly Artisan command (`--dry`, `--module`, `--clean`)

### Procurement Automation
Event-driven document creation across the procurement lifecycle.

- [PurchaseRequisitionObserver](./showcase/Purchases/PurchaseRequisitionObserver.php) — Auto-creates RFQ or PO on PR approval

### Service Layer Pattern
- [ItemService](./showcase/Item/ItemService.php) — Repository + Service + BlackBox integration

### Position-Based RBAC
- [Migration](./showcase/AccessControl/add_position_to_roles_table.php) — Auto-generated roles from positions

---

## 📋 Architecture Decision Records (ADR)

Key architectural decisions documented in [`/docs/adr`](./docs/adr/)

| # | Decision | Status |
|---|---|---|
| 001 | [BlackBox Event-Driven Architecture](./docs/adr/001-blackbox-event-driven-architecture.md) | Accepted |
| 002 | [Service Container in Traits](./docs/adr/002-service-container-in-traits.md) | Accepted |
| 003 | [BlackBox Table Partitioning](./docs/adr/003-blackbox-table-partitioning.md) | Accepted |
| 004 | [Zero-Config Permission Enforcement](./docs/adr/004-auto-detect-permission.md) | Accepted |
