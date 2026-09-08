import { cpSync, existsSync, rmSync } from 'node:fs';

const source = 'public/build';
const target = 'dist';

if (!existsSync(source)) {
    console.error(`Missing ${source}. Run vite build first.`);
    process.exit(1);
}

if (existsSync(target)) {
    rmSync(target, { recursive: true, force: true });
}

cpSync(source, target, { recursive: true });
console.log(`Copied ${source} → ${target} for Hostinger deploy.`);
