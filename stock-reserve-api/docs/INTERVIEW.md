# Interview walkthrough

1. Log in as customer; reserve two units and show stock reduction.
2. Repeat an API call with the same idempotency key. Show the same UUID and unchanged stock.
3. Reuse key with different quantity; show 409.
4. Reserve more than available; show 409 and unchanged tables.
5. Cancel once and again; show stock restored once.
6. Confirm a new reservation; show stock remains consumed and cancellation is rejected.
7. Log in as another customer and show ownership denial.
8. Run expiry tests and explain the scheduled command.
9. Show audit entries as admin and the feature tests.

Prepare answers: Why is `if stock > quantity` in PHP unsafe? Why does a transaction alone not guarantee stock safety? What distinguishes idempotency from caching? What happens if the client loses the response after commit? How does the unique constraint settle a race? Why is SQLite testing not proof of MySQL concurrency? What work would you add before accepting live payments?

After running and extending it, a truthful resume bullet could be:

“Built a Laravel portfolio inventory API with transaction-based reservations, idempotent request handling, Sanctum authentication, ownership checks, audit events, and automated feature tests.”

Do not add traffic, performance, revenue or production reliability claims without measurements. Add one enhancement you understand—for example MySQL concurrency tests—and document the result before presenting it as your own portfolio work.
