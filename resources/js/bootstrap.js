import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

const inflightGets = new Map();
const originalGet = window.axios.get.bind(window.axios);

window.axios.get = function get(url, config = {}) {
    if (config.dedupe === false) {
        return originalGet(url, config);
    }

    const key = `${url}|${JSON.stringify(config.params || {})}`;
    if (inflightGets.has(key)) {
        return inflightGets.get(key);
    }

    const started = performance.now();
    const pending = originalGet(url, config)
        .then((response) => {
            if (import.meta.env.DEV) {
                const ms = performance.now() - started;
                if (ms > 400) {
                    console.warn(`[BACS] Slow API ${Math.round(ms)}ms GET`, url);
                }
            }
            return response;
        })
        .finally(() => inflightGets.delete(key));

    inflightGets.set(key, pending);

    return pending;
};

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
