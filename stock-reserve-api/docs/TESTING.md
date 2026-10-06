# Verification

Run `php artisan test`. Feature tests cover authentication, admin restrictions, idempotent reserve replay, payload conflicts, insufficient-stock rollback, sequential competing requests, repeated cancellation, confirmation conflicts, ownership isolation, expiration, stale confirmation, validation, registration privileges, and login rejection.

Tests use SQLite by default. Sequential competing requests are not a real simultaneous concurrency test. The guarded SQL update and constraints are the design mechanism; demonstrate true concurrency against your intended database before making concurrency/load claims.

To verify on MySQL: create a dedicated disposable test database; set DB_CONNECTION=mysql and its DB_* credentials in a local phpunit.mysql.xml (do not commit secrets); use that config with `vendor/bin/phpunit -c phpunit.mysql.xml`. Run parallel independent processes against a finite-stock product and verify total confirmed/pending quantities plus available stock equals initial stock. Do not use your live database because RefreshDatabase rebuilds tables.

Validation status is recorded in VALIDATION.md when this deliverable is packaged. GitHub CI must complete in your repository before displaying a passing badge.
