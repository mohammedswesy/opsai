import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    use: {
        baseURL: 'http://127.0.0.1:8123',
        trace: 'on-first-retry',
    },
    webServer: {
        command: 'php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8123',
        env: {
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: path.resolve('database/e2e.sqlite'),
            DB_URL: '',
            AI_PROVIDER: 'fake',
            QUEUE_CONNECTION: 'sync',
            WHATSAPP_PROVIDER: 'fake',
            WHATSAPP_VERIFY_TOKEN: 'verify-token',
            WHATSAPP_APP_SECRET: 'app-secret',
        },
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: false,
        timeout: 120_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
        },
    ],
});
