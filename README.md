# OpsAI

Multi-tenant operations platform for field-service and maintenance companies: from the first customer message to the final invoice.

![Dispatch board](docs/screenshots/dispatch.png)

## What it does

- **Multi-tenancy:** every organization has its own branches, team, customers, operations and finances. Policies and role checks enforce company isolation.
- **Operations:** customers, service catalog, technicians and skills, service requests, appointments with overlap detection, work orders, tasks, activity log.
- **Dispatch board:** day timeline per technician, unassigned jobs list, assignment drawer, drag to reschedule through the existing appointment update (so the overlap check still decides), and a rules-based "Best match" suggestion (skill, then utilization, then earliest free slot).
- **Finance:** invoices with sequential numbers, organization legal details (legal name, address, VAT number) on the letterhead, downloadable invoice PDF in Arabic or English, full and partial payments, refunds, overdue handling, finance KPIs.
- **Assistant:** answers in the language of the user's message, reads live data through application tools and can create requests, tasks and appointments only after the user confirms. It has no financial tools by design (no payments, refunds or discounts).
- **WhatsApp:** Meta Cloud API provider with signed-webhook verification, per-organization customer matching, encrypted integration settings, an inbox, hand-off to a human agent, approved message templates outside the 24-hour window, opt-in appointment reminders, and connection-health warnings (for example an expired token).
- **Roles:** owner, admin, manager, dispatcher, technician, accountant, customer service.
- **Languages:** Arabic (RTL) and English.

## Status

Working demo. Backend and UI are covered by automated tests, but two integrations have only been tested with fake providers so far:

| Area | Status |
| --- | --- |
| Operations, dispatch, finance | Implemented and tested locally |
| Invoice PDF with organization letterhead | Implemented and tested |
| Assistant with the fake provider | Implemented and tested |
| Assistant with the live OpenAI API | Not verified yet |
| WhatsApp with the fake provider (messages, templates, reminders) | Implemented and tested |
| WhatsApp with a live Meta account | Not verified yet |
| MySQL 8.4 | Migrations and the full Pest suite (247 tests) pass locally |
| Production deployment, backups | Not done |
| Saudi e-invoicing (ZATCA), card payment gateways | Not implemented |
| Subscriptions and SaaS plans | Not implemented |

## Stack

- Laravel 12, PHP 8.2, session authentication, Laravel Sanctum
- React, TypeScript, Inertia.js, Tailwind CSS v4, Vite
- Pest for backend tests, Playwright for end-to-end tests
- mPDF for invoice PDFs (Arabic and English)
- SQLite for local development and Playwright. The Pest suite also passes on MySQL 8.4, which is the intended production database

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

Or run `composer run dev` to start the app server, queue listener, logs and Vite together. Redis is optional: the default local queue and cache drivers are `database`.

### Tests

```bash
composer test
npx playwright test    # runs against database/e2e.sqlite
```

The Pest suite also passes on MySQL: start a throwaway MySQL container, point the `DB_*` environment variables at it for that one terminal and run `composer test`.

### Richer demo data

`php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder` rewrites the demo company's operational data (dates are relative to the day it runs). It deletes and recreates that company's demo rows, so back up your database file first. Never run `migrate:fresh` on a database you want to keep.

### Live integrations

- Assistant with OpenAI: see [docs/ai-live-check.md](docs/ai-live-check.md).
- WhatsApp with a Meta test number: see [docs/whatsapp-live-setup.md](docs/whatsapp-live-setup.md).

## Demo accounts

Created by the seeders. The password for every demo user is `password`. Use them in development only.

| Email | Organization | Role |
| --- | --- | --- |
| owner@riyadh-cooling.test | Riyadh Cooling & Maintenance | owner |
| admin@riyadh-cooling.test | Riyadh Cooling & Maintenance | admin |
| manager@riyadh-cooling.test | Riyadh Cooling & Maintenance | manager |
| dispatcher@riyadh-cooling.test | Riyadh Cooling & Maintenance | dispatcher |
| technician@riyadh-cooling.test | Riyadh Cooling & Maintenance | technician |
| accountant@riyadh-cooling.test | Riyadh Cooling & Maintenance | accountant |
| support@riyadh-cooling.test | Riyadh Cooling & Maintenance | customer service |
