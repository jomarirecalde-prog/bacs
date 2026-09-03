import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = null;

const key = import.meta.env.VITE_REVERB_APP_KEY;
const host = import.meta.env.VITE_REVERB_HOST;
const userId = document.head.querySelector('meta[name="user-id"]')?.content;

/**
 * Reverb is a local/dev (or dedicated) websocket server. Never open sockets to
 * loopback from a hosted origin (Vercel) — that produces endless
 * ERR_CONNECTION_REFUSED noise and cannot work in production.
 */
function reverbHostIsUsable(candidate) {
    if (!candidate || typeof candidate !== 'string') {
        return false;
    }

    const normalized = candidate.trim().toLowerCase();
    if (!normalized) {
        return false;
    }

    const loopback = normalized === '127.0.0.1'
        || normalized === 'localhost'
        || normalized === '::1'
        || normalized === '0.0.0.0';

    if (!loopback) {
        return true;
    }

    const pageHost = window.location.hostname.toLowerCase();

    return pageHost === '127.0.0.1'
        || pageHost === 'localhost'
        || pageHost === '::1';
}

if (key && host && userId && reverbHostIsUsable(host)) {
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
    const useTls = scheme === 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: useTls,
        enabledTransports: useTls ? ['wss'] : ['ws'],
        authEndpoint: window.appUrl ? `${window.appUrl('/broadcasting/auth')}` : '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrf || '',
                Accept: 'application/json',
            },
        },
    });

    const connection = window.Echo.connector?.pusher?.connection;
    if (connection) {
        connection.bind('connected', () => {
            window.dispatchEvent(new CustomEvent('echo-connected'));
        });
        connection.bind('unavailable', () => {
            window.dispatchEvent(new CustomEvent('echo-disconnected'));
        });
        connection.bind('failed', () => {
            window.dispatchEvent(new CustomEvent('echo-disconnected'));
        });
        connection.bind('error', () => {
            // Swallow; UI already falls back to polling when Echo is down.
        });
    }
}

/**
 * Subscribe to the authenticated user's private channel.
 * Multiple callers share the same Echo channel instance.
 */
window.listenToUser = function listenToUser(event, handler) {
    if (!window.Echo || !userId) {
        return () => {};
    }

    const channel = window.Echo.private(`App.Models.User.${userId}`);
    channel.listen(event, handler);

    return () => {
        try {
            channel.stopListening(event, handler);
        } catch {
            // Channel may already be gone after a full reload.
        }
    };
};

/**
 * Admin attendance dashboards share one private channel.
 */
window.listenToAttendanceDashboard = function listenToAttendanceDashboard(event, handler) {
    if (!window.Echo || !userId) {
        return () => {};
    }

    const channel = window.Echo.private('attendance.dashboard');
    channel.listen(event, handler);

    return () => {
        try {
            channel.stopListening(event, handler);
        } catch {
            // Channel may already be gone after a full reload.
        }
    };
};
