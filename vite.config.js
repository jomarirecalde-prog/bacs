import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

function viteBuildBase(env) {
    const raw = env.ASSET_URL || env.APP_URL || '';
    if (!raw) {
        return '/build/';
    }

    try {
        const pathname = new URL(raw).pathname.replace(/\/$/, '');
        return `${pathname}/build/`;
    } catch {
        return '/build/';
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        base: viteBuildBase(env),
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/landing.js',
                    'resources/js/station.js',
                    'resources/js/qrcode-page.js',
                ],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
