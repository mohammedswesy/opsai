# Testing

`composer test` runs Pest. Feature tests use an in-memory SQLite database and `RefreshDatabase`.

Covered now:

- Guest redirect to login
- Organization registration and owner membership
- Invalid login
- Sanctum token access to `GET /api/user`, and rejection without a token
- Password reset request and token reset
- Branch isolation between two organizations, including a cross-tenant update
- Manager branch creation and technician denial
- Admin blocked from assigning the owner role
- Last owner cannot be removed
- Suspended organization blocks a technician and still allows the owner
- Demo seeder creates both organizations, the Riyadh owner, and the Riyadh main branch

Frontend component tests and Playwright journeys are not set up yet. The critical browser journey depends on customers, technicians, requests, and invoices, which are later phases.
