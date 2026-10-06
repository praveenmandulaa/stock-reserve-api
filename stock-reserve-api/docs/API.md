# API contract

Base URL: `/api/v1`. Send `Accept: application/json` and JSON content type for bodies. All protected endpoints require `Authorization: Bearer TOKEN`.

| Method | Path | Auth | Body / behavior |
|---|---|---|---|
| POST | /register | Public, throttled | name, email, password (12–128 chars); creates customer and token; 201 |
| POST | /login | Public, throttled | email, password; token; 200 |
| POST | /logout | Customer/admin | Revokes current token; 204 |
| GET | /products | Customer/admin | Laravel pagination, 20/page |
| POST | /products | Admin | sku, name, available (0–1,000,000); 201 |
| GET | /reservations | Customer/admin | Only current user's reservations, 20/page |
| POST | /reservations | Customer/admin | product_id, quantity (1–1,000); Idempotency-Key header; 201 or replay 200 |
| POST | /reservations/{uuid}/confirm | Owner | pending → confirmed, or expired if deadline passed |
| POST | /reservations/{uuid}/cancel | Owner | pending → cancelled, or expired if deadline passed |
| GET | /audit | Admin | Audit events, 50/page |
| GET | /up | Public | Framework health endpoint |

Idempotency key: 8–100 characters, letters/numbers/hyphen/underscore. Its uniqueness is per user, not per product. Replaying the original request returns its current reservation state, including expired/cancelled states; it never creates a fresh hold. Use a new key for a genuinely new reservation. The browser retains a key for retries until successful, but page reload loses that key.

Reservation response:

```json
{"data":{"id":"uuid","user_id":1,"product_id":1,"quantity":2,"status":"pending","expires_at":"timestamp","idempotency_key":"keyboard-request-001"},"replayed":false}
```

Transition response is the reservation object directly. Expired confirmation returns HTTP 200 with `status: expired`, not a false success status. A client must inspect status. Repeated confirm of confirmed or cancel of cancelled is idempotent; trying another terminal transition returns 409. Other users see 404 for an owned reservation.

Errors: 401 invalid/missing token, 403 admin-only action, 404 unknown/non-owned reservation, 409 insufficient stock/payload mismatch/terminal conflict, 422 invalid input, 429 throttle. Tokens expire in 8 hours. Logout only revokes the current token.

Pagination: pass `?page=2`; UI currently displays the first page for this small demo. The API supports all pages.
