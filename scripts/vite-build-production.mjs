import { execSync } from 'node:child_process';

process.env.APP_URL = process.env.APP_URL || 'https://bacsconstruction.com';
process.env.ASSET_URL = process.env.ASSET_URL || 'https://bacsconstruction.com';

execSync('vite build', { stdio: 'inherit', env: { ...process.env } });
execSync('node scripts/sync-hostinger-dist.mjs', { stdio: 'inherit' });
