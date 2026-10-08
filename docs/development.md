# Development

## Local database

The default `.env` uses SQLite. Create the file if it is missing, then migrate and seed:

```bash
php artisan migrate:fresh --seed
```

For MySQL, set `DB_CONNECTION=mysql` and the `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values. The migrations use portable column types.

## Frontend

Pages live in `resources/js/pages`. Shared layout components live in `resources/js/layouts` and `resources/js/components`. Copy is in `resources/js/i18n/en.ts` and `resources/js/i18n/ar.ts`.

`npm run dev` starts Vite. `npm run types` runs the TypeScript checker.

## Queues

`composer run dev` starts `php artisan queue:listen`. Phase 1 does not dispatch domain jobs. The jobs table exists for later notifications and integrations.

## Code style

```bash
vendor\bin\pint
```
