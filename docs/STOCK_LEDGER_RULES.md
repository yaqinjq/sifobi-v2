# Stock Ledger Rules

## Core Rules

- All stock mutations must go through `App\Services\StockLedgerService`.
- No MySQL trigger may create, update, or rebalance stock.
- `stock_mutations` are immutable.
- Existing stock mutations must not be edited or deleted.
- Cancellation must create a new mutation with type `VOID_REVERSAL`.
- Every stock process must run in a database transaction.

## Required Ledger Fields

- `tenant_id`
- `outlet_id`
- `item_id`
- `mutation_type`
- `qty_change`
- `unit_id`
- `reference_type`
- `reference_id`
- `performed_by`
- `performed_at`

## Quantity Semantics

- Positive `qty_change` means stock increases.
- Negative `qty_change` means stock decreases.
- `OPEN_STOCK` can be positive or zero as an initial posted quantity.
- `GOODS_RECEIVE` must increase stock.
- `SPOIL_WASTE` must decrease stock.
- `DAILY_OPNAME_ADJ` and `MONTHLY_OPNAME_ADJ` may be positive or negative.
- `VOID_REVERSAL` must reverse a previous mutation quantity.

## Balance Rules

- `stock_balances` stores the current balance for `(tenant_id, outlet_id, item_id)`.
- Balance update must happen after inserting the stock mutation and inside the same transaction.
- Balance rows should be locked during update where the database supports row locks.

## Audit Rules

- Store `performed_by` and `performed_at`.
- Store reference type and id for traceability.
- Store optional notes and metadata for operational context.
- Future approvals should append events instead of rewriting posted stock history.

