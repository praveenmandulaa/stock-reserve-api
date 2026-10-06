# Architecture and tradeoffs

## Tables

- users: normal Laravel user plus `is_admin` (never mass assignable from API registration).
- products: unique SKU, name, available stock.
- reservations: UUID, user/product foreign keys, quantity, idempotency key, status, expiry.
- audit_events: reservation foreign key, event name, timestamp.
- personal_access_tokens: Sanctum token hashes and expiry.

## Reserve transaction

1. Look up prior user/key. Reject different payload, otherwise replay.
2. Insert pending reservation; the database's unique constraint arbitrates concurrent duplicates.
3. Atomically decrement inventory only when available >= requested quantity.
4. If no row changed, abort 409: transaction rolls back both reservation and inventory.
5. Write audit record and commit.

Laravel retries transaction deadlocks up to 3 attempts. Duplicate-key recovery reads the winning reservation after rollback. Unrelated integrity errors are rethrown. SQLite has coarse writer locking and can return busy errors under parallel load; database engine concurrency behavior must be tested separately.

## Terminal transition

Look up the caller-owned reservation using a row lock. If deadline passed, expire it. Guard the update on pending status. Cancellation/expiry restore inventory only if that update won, in the same transaction. Audit writes commit with it. Confirmation keeps inventory consumed. Expiry scanner rechecks each candidate inside its own transaction so stale candidates cannot restore stock twice.

## Scheduling

`inventory:expire` is a synchronous Artisan command, scheduled every minute. This project does not claim queue implementation. `withoutOverlapping` uses the configured cache; use a shared cache if scheduling across instances and configure only one scheduler. Atomic state guards still protect repeated execution.

## Why keep the UI small?

The main contribution is backend correctness. A same-origin browser UI demonstrates it without an npm dependency or external CDN. Tokens stay in JavaScript memory; reload requires login. API mutations use bearer authentication rather than browser cookie authentication. The public registration endpoint only creates customers.

## Upgrade roadmap

1. Add a MySQL CI job and multi-process reservation tests.
2. Add warehouse stock movements and reconciliation rather than editing stock directly.
3. Add signed payment webhooks, retaining receipt idempotency and verifying amount/currency.
4. Add asynchronous notifications only after transaction commit.
5. Add OpenAPI, metrics, structured logs, token cleanup, and deployment automation.
