/**
 * Authenticated web session management for BACS.
 *
 * - Keeps sessions alive while the tab is visible and the user is active
 * - Refreshes CSRF tokens from heartbeat / response headers
 * - Warns before idle timeout and allows secure extension
 * - Handles 419 / 401 responses from axios and fetch
 */

const HEARTBEAT_INTERVAL_MS = 5 * 60 * 1000;
const ACTIVITY_IDLE_MS = 30 * 60 * 1000;
const CSRF_STORAGE_KEY = 'bacs-csrf-sync';
const EXPIRY_STORAGE_KEY = 'bacs-session-expiry';

let lastActivity = Date.now();
let expiresAt = null;
let warnBeforeMinutes = 5;
let heartbeatTimer = null;
let warningTimer = null;
let warningShown = false;
let started = false;

function isAuthenticatedShell() {
    return Boolean(document.querySelector('meta[name="user-id"]')?.content);
}

function heartbeatUrl() {
    return window.appUrl?.('/session/heartbeat') || '/session/heartbeat';
}

function extendUrl() {
    return window.appUrl?.('/session/extend') || '/session/extend';
}

function loginUrl() {
    return window.appUrl?.('/login') || '/login';
}

export function applyCsrfToken(token) {
    if (!token) {
        return;
    }

    const meta = document.head.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.content = token;
    }

    if (window.axios?.defaults?.headers?.common) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }

    document.querySelectorAll('input[name="_token"]').forEach((input) => {
        input.value = token;
    });

    try {
        localStorage.setItem(CSRF_STORAGE_KEY, JSON.stringify({ token, at: Date.now() }));
    } catch {
        // localStorage may be unavailable.
    }
}

function readCsrfFromResponse(response) {
    const header = response.headers?.get?.('X-CSRF-TOKEN')
        || response.headers?.['x-csrf-token'];
    if (header) {
        applyCsrfToken(header);
    }
}

function syncFromStorage(event) {
    if (event.key === CSRF_STORAGE_KEY && event.newValue) {
        try {
            const parsed = JSON.parse(event.newValue);
            if (parsed.token) {
                applyCsrfToken(parsed.token);
            }
        } catch {
            // Ignore malformed sync payloads.
        }
    }

    if (event.key === EXPIRY_STORAGE_KEY && event.newValue) {
        try {
            const parsed = JSON.parse(event.newValue);
            if (parsed.expires_at) {
                expiresAt = parsed.expires_at * 1000;
                scheduleWarning();
            }
        } catch {
            // Ignore malformed sync payloads.
        }
    }
}

function noteActivity() {
    lastActivity = Date.now();
    if (warningShown) {
        hideWarning();
    }
}

function shouldHeartbeat() {
    if (document.hidden) {
        return false;
    }

    return Date.now() - lastActivity < ACTIVITY_IDLE_MS;
}

export async function refreshSession({ force = false } = {}) {
    if (!isAuthenticatedShell()) {
        return null;
    }

    if (!force && !shouldHeartbeat()) {
        return null;
    }

    try {
        const response = await fetch(heartbeatUrl(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        readCsrfFromResponse(response);

        if (response.status === 401 || response.redirected) {
            handleSessionExpired();
            return null;
        }

        if (!response.ok) {
            return null;
        }

        const data = await response.json();
        if (data.csrf_token) {
            applyCsrfToken(data.csrf_token);
        }
        if (data.expires_at) {
            expiresAt = data.expires_at * 1000;
            warnBeforeMinutes = data.warn_before_minutes ?? warnBeforeMinutes;
            try {
                localStorage.setItem(EXPIRY_STORAGE_KEY, JSON.stringify({
                    expires_at: data.expires_at,
                    at: Date.now(),
                }));
            } catch {
                // localStorage may be unavailable.
            }
            scheduleWarning();
        }

        return data;
    } catch {
        return null;
    }
}

function scheduleWarning() {
    clearTimeout(warningTimer);

    if (!expiresAt) {
        return;
    }

    const warnAt = expiresAt - (warnBeforeMinutes * 60 * 1000);
    const delay = warnAt - Date.now();

    if (delay <= 0) {
        if (shouldHeartbeat()) {
            showWarning();
        }
        return;
    }

    warningTimer = setTimeout(() => {
        if (shouldHeartbeat()) {
            showWarning();
        }
    }, delay);
}

function showWarning() {
    if (warningShown || !isAuthenticatedShell()) {
        return;
    }

    warningShown = true;
    window.dispatchEvent(new CustomEvent('bacs:session-warning', {
        detail: { expiresAt },
    }));
}

function hideWarning() {
    warningShown = false;
    window.dispatchEvent(new CustomEvent('bacs:session-warning-hide'));
}

export function handleSessionExpired(message) {
    hideWarning();
    window.dispatchEvent(new CustomEvent('bacs:session-expired', {
        detail: { message: message || 'Your BACS session has expired. Please sign in again.' },
    }));
}

export function handleUnauthorized(detail) {
    if (detail?.code === 'SESSION_EXPIRED' || detail?.message?.toLowerCase?.().includes('session')) {
        handleSessionExpired(detail?.message);
        return;
    }

    window.dispatchEvent(new CustomEvent('bacs:unauthorized', { detail }));
}

async function extendSession() {
    try {
        const token = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(extendUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
        });

        readCsrfFromResponse(response);

        if (!response.ok) {
            handleSessionExpired();
            return false;
        }

        const data = await response.json();
        if (data.csrf_token) {
            applyCsrfToken(data.csrf_token);
        }
        if (data.expires_at) {
            expiresAt = data.expires_at * 1000;
            scheduleWarning();
        }

        noteActivity();
        hideWarning();
        return true;
    } catch {
        handleSessionExpired();
        return false;
    }
}

function onVisibilityChange() {
    if (!document.hidden) {
        noteActivity();
        refreshSession({ force: true });
    }
}

function onFormSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (form.method.toLowerCase() !== 'post') {
        return;
    }
    if (!isAuthenticatedShell()) {
        return;
    }

    const token = document.head.querySelector('meta[name="csrf-token"]')?.content;
    const formToken = form.querySelector('input[name="_token"]')?.value;
    if (token && formToken && token !== formToken) {
        form.querySelector('input[name="_token"]').value = token;
    }
}

async function onFormSubmitCapture(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
        return;
    }
    if (!isAuthenticatedShell() || form.dataset.sessionRefresh === 'skip') {
        return;
    }

    const age = Date.now() - lastActivity;
    if (age > 10 * 60 * 1000 || (expiresAt && Date.now() > expiresAt - (warnBeforeMinutes * 60 * 1000))) {
        event.preventDefault();
        await refreshSession({ force: true });
        const token = document.head.querySelector('meta[name="csrf-token"]')?.content;
        const input = form.querySelector('input[name="_token"]');
        if (token && input) {
            input.value = token;
        }
        form.dataset.sessionRefresh = 'skip';
        form.requestSubmit();
    }
}

function startHeartbeat() {
    clearInterval(heartbeatTimer);
    heartbeatTimer = setInterval(() => {
        refreshSession();
    }, HEARTBEAT_INTERVAL_MS);
}

function bindActivityListeners() {
    const events = ['mousedown', 'keydown', 'touchstart', 'scroll', 'click'];
    let scheduled = false;

    const onEvent = () => {
        if (scheduled) {
            return;
        }
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            noteActivity();
        });
    };

    events.forEach((name) => {
        document.addEventListener(name, onEvent, { passive: true });
    });

    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('submit', onFormSubmit, true);
    document.addEventListener('submit', onFormSubmitCapture, true);
    window.addEventListener('storage', syncFromStorage);
    window.addEventListener('bacs:pageshow', () => refreshSession({ force: true }));
    window.addEventListener('bacs:session-extend', () => extendSession());
    window.addEventListener('bacs:session-logout', () => {
        const token = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
        const logoutUrl = window.appUrl?.('/logout') || '/logout';
        fetch(logoutUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).finally(() => {
            window.location.assign(loginUrl());
        });
    });
}

export function bootSession() {
    if (started || !isAuthenticatedShell()) {
        return;
    }

    started = true;
    bindActivityListeners();
    startHeartbeat();
    refreshSession({ force: true });
}

export function installAxiosSessionHandling(axios) {
    axios.interceptors.response.use(
        (response) => {
            readCsrfFromResponse(response);
            return response;
        },
        (error) => {
            const status = error.response?.status;
            const data = error.response?.data;

            if (status === 419) {
                handleSessionExpired(data?.message);
            } else if (status === 401) {
                handleUnauthorized(data);
            } else if (status === 403) {
                window.dispatchEvent(new CustomEvent('bacs:forbidden', { detail: data }));
            }

            return Promise.reject(error);
        },
    );
}
