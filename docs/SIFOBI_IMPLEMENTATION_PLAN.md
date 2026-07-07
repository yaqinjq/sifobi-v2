# SIFOBI v2 Implementation Plan

## Audit Snapshot

- Project path: `F:/herd/sifobi`
- PHP CLI: `8.4.20`
- Laravel runtime: `13.17.0`
- Composer requirement: PHP `^8.3`, Laravel `^13.8`
- Frontend: Vite, Tailwind CSS 4 via `@tailwindcss/vite`
- Auth starter kit: not installed; project still uses default Laravel skeleton routes/views
- Database example: `.env.example` defaults to sqlite
- Local `.env` note: `DB_CONNECTION=MySQL` is not a valid Laravel connection key; use `mysql`

## Phase Scope

### Phase 0 - Audit and Documentation

- Record project baseline and constraints.
- Define architecture decisions before adding business modules.
- Define mobile UI rules for outlet operations.
- Define immutable stock ledger rules.

### Phase 1 - Foundation

- Add modular namespaces under `app/Modules`.
- Add core tenant, organization, outlet, inventory, stock, operations, receiving, and early procurement tables.
- Keep tables tenant-aware with `tenant_id` on operational and master data.
- Avoid MySQL triggers and keep business rules in Laravel services.

### Phase 2 - RBAC

- Use Spatie Laravel Permission.
- Seed minimum roles and permissions.
- Keep finance/admin/report access broader than staff outlet roles.
- Allow staff Bar, Kitchen, and Gudang to create PO.
- Allow PIC Outlet to approve PO for WIP, Roastery, and Central Kitchen flow.

### Phase 3 - Stock Service Layer

- Add `StockLedgerService`.
- All stock changes must be written as immutable `stock_mutations`.
- Balance updates must happen in the same database transaction.
- Cancellation must create `VOID_REVERSAL`, never update or delete old mutations.

## Deferred Scope

- POS, recipe costing, full HPP, production costing, and accounting are not implemented in this phase.
- Stock transfer is not implemented yet. Future design must support normal transfer and outlet-to-outlet sale/purchase transfer.
- OCR/photo parsing automation is not implemented yet; document capture tables are prepared as foundation only.
- Full auth UI and mobile operation screens are deferred to Phase 4+.

