import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = null;

const key = import.meta.env.VITE_REVERB_APP_KEY;
const host = import.meta.env.VITE_REVERB_HOST;
const userId = document.head.querySelector('meta[name="user-id"]')?.content;

if (key && host && userId) {
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
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
