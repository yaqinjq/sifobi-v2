# Architecture Decisions

## ADR-001: Laravel Service Layer Owns Business Rules

Business rules live in Laravel services, not in MySQL triggers. This keeps behavior testable, reviewable, and easier to evolve for SaaS or queue/event workflows.

## ADR-002: Tenant-Aware Tables From the Start

Master and transaction tables include `tenant_id`. The first implementation can run as a single tenant, but the schema remains ready for multi-brand, multi-outlet, multi-PT, and SaaS segmentation.

## ADR-003: Modular Namespace Without Extra Package

Modules are placed under `app/Modules/{Domain}` and autoload through Laravel's existing `App\\` namespace. This keeps the codebase modular without adding a heavy module framework.

Initial modules:

- `Core`
- `Inventory`
- `Stock`
- `Receiving`
- `Operations`
- `Procurement`
- `Production`
- `Reports`

## ADR-004: Immutable Stock Ledger

Stock movement is recorded as immutable ledger rows. Corrections use compensating mutations. This preserves auditability for outlet operations, finance review, and future accounting integration.

## ADR-005: Decimal Precision

Quantities use high precision decimals, currently `decimal(18, 6)`. Monetary values use `decimal(19, 4)` unless a later accounting decision requires another standard.

## ADR-006: Canonical Item and Alias Model

`items` store canonical SKU and item identity. Legacy codes or outlet/brand-specific codes are stored in `item_aliases`, so old FBI/OCIA/POS codes do not fragment the item master.

## ADR-007: Operational UI Is Mobile First

Outlet workflows are designed for phone use first, especially iPhone Safari. Desktop support remains responsive, but staff operation screens should not depend on wide tables.

## ADR-008: Future Transfer Design

Stock transfer is deferred. Future transfer design must distinguish normal internal transfer from outlet-to-outlet sale/purchase transfer, because legal entity, brand, and finance implications differ.

## ADR-009: Recipe, HPP, POS, and Accounting

Recipe costing, HPP, POS, and accounting modules are deferred. The stock ledger should expose clean references and mutation types so future modules can integrate without rewriting inventory history.

