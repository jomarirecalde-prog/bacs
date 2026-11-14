import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

function readPackageVersion() {
    const pkg = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8'));
    const version = pkg?.version;

    if (typeof version !== 'string' || !/^\d+\.\d+\.\d+/.test(version)) {
        throw new Error('package.json must define a semver "version" (e.g. "1.0.1").');
    }

    return version;
}

function syncServiceWorker(version) {
    const path = join(root, 'public', 'sw-app.js');
    const swToken = `bacs-app-v${version}`;
    let source = readFileSync(path, 'utf8');

    const pattern = /^const SW_VERSION = 'bacs-app-v[^']+';/m;

    if (!pattern.test(source)) {
        throw new Error(`Could not find SW_VERSION in ${path}`);
    }

    const next = source.replace(pattern, `const SW_VERSION = '${swToken}';`);

    if (next !== source) {
        writeFileSync(path, next, 'utf8');
    }
}

function syncWebManifest(version) {
    const path = join(root, 'public', 'app.webmanifest');
    const manifest = JSON.parse(readFileSync(path, 'utf8'));

    if (manifest.version === version) {
        return;
    }

    manifest.version = version;
    writeFileSync(path, `${JSON.stringify(manifest, null, 4)}\n`, 'utf8');
}

export function syncPwaVersion() {
    const version = readPackageVersion();
    syncServiceWorker(version);
    syncWebManifest(version);
    return version;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
    const version = syncPwaVersion();
    console.log(`PWA version synced to ${version} (sw-app.js, app.webmanifest).`);
}