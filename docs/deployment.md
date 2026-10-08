# Deployment

This phase can be deployed as a normal Laravel application. It is not a finished commercial operations product.

## Requirements

- PHP 8.2+
- MySQL 8
- A web server pointing at `public`
- Node.js to build assets with `npm run build`
- A process supervisor for `php artisan queue:work` once jobs exist
- Cron for `php artisan schedule:run` once scheduled tasks exist

Redis is optional until the queue or cache driver is switched to `redis`.

## Release steps

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_KEY`. Do not seed the demo accounts in production.

Set `APP_URL` to the public origin. Sanctum's stateful domains should include that host when a first-party SPA calls the API with cookies.

Mail must be a real mailer before password reset is used by customers. The local default is the `log` mailer.

AI, WhatsApp, and payment secrets can stay empty until those integrations are implemented.
