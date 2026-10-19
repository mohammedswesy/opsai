# OpsAI

OpsAI is a multi-tenant operations platform for service businesses. The current build covers organizations, operations, finance, and an assistant that reads live data through application tools. WhatsApp and card payment gateways are not built yet.

## Stack

- Laravel 12, PHP 8.2, session authentication, Laravel Sanctum for API tokens
- React, TypeScript, Inertia, Tailwind CSS, Vite
- Pest for backend tests
- SQLite for local development and tests. MySQL is the intended production database and is already configured in `config/database.php`

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

Or use `composer run dev` to start the app server, queue listener, logs, and Vite together.

Redis is configured in `.env.example` but the default local queue and cache drivers are `database`, so the app runs without Redis.

## Demo accounts

These accounts are created by `php artisan db:seed`. The password for every demo user is `password`. Use them only in development.

| Email | Organization | Role |
| --- | --- | --- |
| owner@riyadh-cooling.test | Riyadh Cooling Services | owner |
| manager@riyadh-cooling.test | Riyadh Cooling Services | manager |
| dispatcher@riyadh-cooling.test | Riyadh Cooling Services | dispatcher |
| technician@riyadh-cooling.test | Riyadh Cooling Services | technician |
| accountant@riyadh-cooling.test | Riyadh Cooling Services | accountant |
| support@riyadh-cooling.test | Riyadh Cooling Services | customer service |
| owner@jeddah-maintenance.test | Jeddah Maintenance Pro | owner |

## Assistant

Copy the AI settings from `.env.example`. `AI_PROVIDER=fake` answers from the application without calling an external model. Set `AI_PROVIDER=openai` and `AI_API_KEY` only on the server when you want the OpenAI adapter. Do not put the key in frontend code. Run `php artisan ai:health-check` on a development machine that has a key. See `docs/ai.md`.

## Tests

```bash
composer test
npm run types
npm run build
```

## Current product boundary

Implemented: registration, login, password reset, organization settings, team membership, branch management, role checks, and organization-scoped branch queries.

Not implemented: WhatsApp, card payment gateways, and invoice PDF files. See `docs/product-roadmap.md` and `docs/ai.md`.
