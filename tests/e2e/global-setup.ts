import { existsSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const database = path.resolve('database/e2e.sqlite');

export default function globalSetup(): void {
    if (!existsSync(database)) {
        writeFileSync(database, '');
    }
}
