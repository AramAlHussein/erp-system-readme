# Advanced ERP Core - A Modern Business Management Platform

**ERP Core** is a robust, scalable, and secure platform designed to serve as the central nervous system for a modern enterprise. Engineered with a focus on automation, granular control, and data integrity, this system provides a comprehensive solution for managing Human Resources, Inventory, Procurement, and Sales.

## 🎯 The "Why": Solving Real-World Business Challenges

Standard software often fails to capture the complexity of real business logic. This ERP Core was built from the ground up to address critical operational pain points:

*   **Prevent Permission Chaos:** Implements a strict, role-based access control system to eliminate unauthorized data access and ensure users only see what they need.
*   **Eliminate Human Error:** Automates repetitive tasks like ID generation, status updates, and inter-departmental data flow, significantly reducing costly mistakes.
*   **Ensure Full Traceability:** Provides a comprehensive audit log for every critical action, guaranteeing transparency and accountability across all operations.
*   **Break Down Data Silos:** Creates a single source of truth by seamlessly integrating all modules, ensuring data flows logically and automatically between departments.

## 🏛️ Architectural Principles & Design Philosophy

This system is not just a collection of features; it's built on a foundation of proven software engineering principles.

*   **Role-Based Access Control (RBAC):** Permissions are intelligently assigned to **Positions (Roles)**, not individuals. This makes managing access for hundreds of users efficient, scalable, and secure.
*   **Smart Automation & Workflows:** Complex business processes, like the entire procurement lifecycle, are automated to reflect real-world logic, from request to payment.
*   **Multi-Layered Security:** Security is paramount, with granular permissions that extend down to the level of individual warehouse access, ensuring data is protected at every layer.
*   **Scalable by Design:** The modular architecture allows for easy expansion and customization, enabling the addition of new features or entire modules without destabilizing the core system.
*   **Single Source of Truth:** The database schema and application logic are designed to maintain data consistency and reliability across all modules, preventing data duplication and conflicts.

## 📦 Core Modules

### 1. Human Resources (HR Management [CORE])
A powerful module for efficiently managing the complete organizational structure and employee lifecycle.

*   **Hierarchical Department Management:** Create and manage a nested department structure, with department heads automatically assigned based on their designated position.
*   **Dynamic Positions & Roles:** Define positions as "Unique" (one occupant) or "General." Sensitive roles like "Department Manager" are protected from accidental modification or deletion.
*   **Automated Employee Onboarding:**
    *   **Unique ID Generation:** Automatically generates a unique, structured employee ID (`DeptCode-PositionCode-ID`) to prevent duplicates and ensure consistency.
    *   **Automatic User Account Creation:** Instantly creates a system user account with a unique username and a secure, default password upon new employee creation.

### 2. Inventory & Warehouse Management
Provides absolute control and visibility over every item in your inventory.

*   **Secure Warehouse Structuring:** Each warehouse is linked to a corresponding "Department," creating an additional security layer that governs inventory access rights.
*   **Advanced Item Management:**
    *   Features both a **tree view** for easy hierarchical organization and a **table view** with advanced search and filtering.
    *   Manages comprehensive item data, including images, file attachments, multiple units of measure, and stock thresholds.
*   **Real-Time Stock Tracking:** Meticulously tracks inventory levels across different states: **On-Hand**, **Reserved**, and **Incoming**. Stock levels are updated automatically based on the status of operations (e.g., approved, rejected).
*   **Controlled Operations Workflow:** Enforces a strict lifecycle for inventory transactions (`Draft` → `Processing` → `Completed`). This ensures that stock is reserved and all approvals are secured before any physical movement occurs.

### 3. Procurement
A fully automated procurement cycle, from request to payment.

*   **Comprehensive Supplier Database:** Manages vendors, their classifications, product catalogs, price lists, currencies, and tax information.
*   **Fully Automated Lifecycle:**
    *   `Purchase Request (PR)` → `Request for Quotation (RFQ)` → `Purchase Order (PO)`.
    *   `Goods Receipt Note (GRN)` with support for partial deliveries and instant inventory updates.
    *   Meticulous management of **Invoices** and **Return** processes.
*   **Complete Transparency:** Every step in the procurement workflow is recorded in an **Audit Log**, ensuring full traceability and accountability.

### 4. Sales
A streamlined and effective module for managing the sales process.

*   Manages customer data, currencies, and invoicing.
*   Integrates seamlessly with the core RBAC system to ensure all sales operations are monitored, controlled, and documented.

## 🔬 Architecture Showcase

Selected code samples demonstrating key architectural decisions.

### 1. BlackBox Audit System
Every operation in the system is logged in three stages:
- **Trying** → before execution
- **Success** → after completion  
- **Failed** → on error, with full exception snapshot

Implementation via a reusable Trait + Event-driven architecture:

[HandlesWarehouseBlackBox.php](./showcase/BlackBox/HandlesWarehouseBlackBox.php)

### 2. Service Layer Pattern
Clean separation between Repository and Service layers,
with Interface contracts enforcing dependency inversion:

[ItemService.php](./showcase/Item/ItemService.php)

### 3. Position-Based RBAC Migration
Roles auto-generated from positions — no manual role management:

[Migration Sample](./showcase/AccessControl/add_position_to_roles_table.php)
