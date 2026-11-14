import { execSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';

process.env.APP_URL = process.env.APP_URL || 'https://bacsconstruction.com';
process.env.ASSET_URL = process.env.ASSET_URL || 'https://bacsconstruction.com';

execSync('vite build', { stdio: 'inherit', env: { ...process.env } });
execSync('node scripts/sync-hostinger-dist.mjs', { stdio: 'inherit' });

const manifestPath = 'dist/manifest.json';
if (existsSync(manifestPath)) {
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const appEntry = manifest['resources/js/app.js']?.file;
    if (appEntry) {
        const appJs = readFileSync(`dist/${appEntry}`, 'utf8');
        if (appJs.includes('"/BACS/public/build/"')) {
            console.error(
                'Production build still uses local XAMPP paths (/BACS/public/build/).\n' +
                    'Unset APP_URL in .env.production or pass ASSET_URL=https://bacsconstruction.com',
            );
            process.exit(1);
        }
    }
}
