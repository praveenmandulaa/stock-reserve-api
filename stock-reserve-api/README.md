# StockReserve — Inventory Reservation API

A Laravel 12 portfolio application for reserving limited inventory without decrementing stock twice on retries. Includes a small browser dashboard and a versioned JSON API.

## Features implemented

- Customer registration and login using expiring Laravel Sanctum bearer tokens.
- Admin-only product creation and audit listing; customer-owned reservations.
- Atomic guarded stock decrement in a database transaction.
- Idempotency keys scoped to each user, with payload conflict detection.
- Reservation confirmation, cancellation and 15-minute expiry.
- Cancellation/expiry restore stock at most once; confirmation keeps it consumed.
- Audit events written in the same transaction as inventory changes.
- Paginated endpoints, validation and API throttling.
- Browser demo with tokens kept in memory, not localStorage.
- Feature tests and a GitHub Actions workflow.

## Quick start (Windows / XAMPP / Laravel Herd / Linux)

Requirements: **PHP 8.2+**, Composer 2, and PHP extensions `pdo_sqlite`, `mbstring`, `xml`, `ctype`, `fileinfo`, `openssl`, `tokenizer`. Node.js is not required.

Extract this repository into a folder outside any existing project. In its terminal:

```sh
composer install
```

Copy `.env.example` to `.env` using your file manager (or `cp .env.example .env`). Set `DEMO_PASSWORD` in `.env` to a password with at least 12 characters, for example a newly generated local-only password. Do not commit `.env`.

```sh
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve
```

Open **http://localhost:8000**. Log in as `admin@example.test` or `customer@example.test`, using your DEMO_PASSWORD. Or create a new customer account in the dashboard.

If XAMPP reports PHP older than 8.2, install a compatible PHP runtime; this project cannot run on PHP 7 or PHP 8.0/8.1. Enable the SQLite and mbstring extensions in your CLI php.ini if missing.

### Run reservation expiry

For development, use a second terminal:

```sh
php artisan schedule:work
```

Or release expired reservations once:

```sh
php artisan inventory:expire
```

An expired reservation remains unavailable until the command runs or the customer attempts a transition. There is no payment system: confirmation is a domain action, not proof of payment.

### Tests

```sh
php artisan test
```

The default test configuration uses in-memory SQLite. See `docs/TESTING.md` for verification and limitations.

## API example

```sh
curl -X POST http://localhost:8000/api/v1/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"customer@example.test","password":"YOUR_LOCAL_DEMO_PASSWORD"}'
```

Copy the returned token, then:

```sh
curl -X POST http://localhost:8000/api/v1/reservations \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: keyboard-request-001' \
  -d '{"product_id":1,"quantity":2}'
```

Repeat the exact request with the same key: the server returns the same reservation with `replayed: true`. Different input with the same key returns 409.

## Design

HTTP controller → InventoryService → database transaction → inventory + reservation + audit.

`UPDATE products SET available = available - quantity WHERE available >= quantity` is a single guarded operation. The reservation insert and stock change commit together. A unique `(user_id, idempotency_key)` constraint arbitrates duplicate requests. Terminal transitions guard `status = pending`, and restore inventory within the same transaction.

Read [API](docs/API.md), [architecture](docs/ARCHITECTURE.md), [testing](docs/TESTING.md), and [interview walkthrough](docs/INTERVIEW.md).

## Scope and limitations

This is a working portfolio demo, **not a production-certified inventory system**. SQLite simplifies local setup; use MySQL/InnoDB or PostgreSQL and concurrent load testing before real deployment. Implement restocking, payment webhooks, email verification/password reset, monitoring, long-term token cleanup, and inventory reconciliation before expanding it into a commercial service. Stock changes are reservations only; the admin can create new products but cannot directly edit existing stock.

Keep the Laravel 12 dependency line for this PHP 8.2-compatible demo. The official docs currently identify Laravel 13 as the newer major; upgrading is a useful follow-up, after verifying its PHP requirements and migration guide.

## Publish to GitHub

1. Create a repository named `stock-reserve-api` on GitHub with no initial README.
2. Extract this project, open its folder, and run:

```sh
git init
git add .
git commit -m "Build transactional inventory reservation API"
git branch -M main
git remote add origin https://github.com/praveenkumarmandula/stock-reserve-api.git
git push -u origin main
```

Confirm `.env`, `vendor/`, local database files and logs are ignored before pushing. The ZIP excludes them. Add screenshots after running it locally, write a short explanation of your own changes, and pin it after you can demonstrate the behavior.

Suggested description: `Laravel inventory reservation API with transactional stock updates, idempotency, Sanctum authentication, audit events, and feature tests.`

Suggested topics: `php`, `laravel`, `rest-api`, `inventory`, `sanctum`, `idempotency`, `phpunit`.

The application was developed with AI assistance. Read, run and modify it before claiming independent authorship or professional deployment experience. Laravel's application skeleton and dependencies retain their original MIT licensing. Custom portfolio source is provided under MIT; see LICENSE.
