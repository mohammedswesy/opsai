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

Operations coverage includes authentication, authorization, customer isolation, catalog ownership, cross-organization technician assignment, appointment overlap, and work-order status transitions.

Finance coverage includes invoice calculation, numbering, authorization, tenant isolation, work-order invoicing, partial and full payments, cancelled invoices, overdue marking, customer balances, and branch-filtered dashboard metrics.

`npm run e2e` runs Playwright against `php artisan serve`. The suite logs in through the browser, walks the operational happy path, invoices a completed work order, records payments, checks that one organization cannot open another organization's records, and checks that a technician cannot create customers or invoices. `tests/e2e/global-setup.ts` refreshes and seeds the database before those tests.
