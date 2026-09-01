export const PWA_VERSION = '1.0.0';

const DISMISS_KEY = 'bacs-pwa-install-dismissed';
const DISMISS_DAYS = 14;

let deferredPrompt = null;
let registration = null;

function isIos() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function wasInstallDismissed() {
    try {
        const raw = localStorage.getItem(DISMISS_KEY);
        if (!raw) {
            return false;
        }
        const dismissedAt = Number(raw);
        if (!Number.isFinite(dismissedAt)) {
            return false;
        }
        return Date.now() - dismissedAt < DISMISS_DAYS * 86400000;
    } catch {
        return false;
    }
}

function dismissInstallPrompt() {
    try {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch {
        // localStorage may be unavailable.
    }
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleString();
    } catch {
        return '—';
    }
}

function refreshPwaStore() {
    const store = window.Alpine?.store('pwa');
    if (!store) {
        return;
    }

    store.online = navigator.onLine;
    store.installed = isStandalone();
    store.canInstall = Boolean(deferredPrompt) && !store.installed;
    store.showIosGuide = isIos() && !store.installed && !store.canInstall;
    store.updateAvailable = Boolean(registration?.waiting);
    store.showBanner = !store.installed
        && (store.canInstall || store.showIosGuide)
        && !wasInstallDismissed();
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    if (window.location.pathname.startsWith('/attendance-station')) {
        return null;
    }

    try {
        registration = await navigator.serviceWorker.register('/sw-app.js', { scope: '/' });

        registration.addEventListener('updatefound', () => {
            const worker = registration.installing;
            if (!worker) {
                return;
            }

            worker.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                    refreshPwaStore();
                }
            });
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (window.__bacsPwaReloading) {
                return;
            }
            window.__bacsPwaReloading = true;
            window.location.reload();
        });

        refreshPwaStore();
        return registration;
    } catch {
        return null;
    }
}

export function bootPwa(Alpine) {
    Alpine.store('pwa', {
        version: PWA_VERSION,
        online: navigator.onLine,
        installed: isStandalone(),
        canInstall: false,
        showIosGuide: false,
        updateAvailable: false,
        checkingUpdate: false,
        lastChecked: null,
        showBanner: false,
        get connectionLabel() {
            return this.online ? 'Online' : 'Offline';
        },
        get installStatusLabel() {
            return this.installed ? 'Installed' : 'Browser';
        },
        get lastCheckedLabel() {
            return formatDateTime(this.lastChecked);
        },
        async install() {
            if (this.installed) {
                return;
            }

            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                refreshPwaStore();

                if (outcome === 'accepted') {
                    this.installed = true;
                }
                return;
            }

            if (this.showIosGuide) {
                document.getElementById('bacs-app')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
        dismissBanner() {
            dismissInstallPrompt();
            this.showBanner = false;
        },
        async checkForUpdates() {
            if (!registration) {
                registration = await registerServiceWorker();
            }

            if (!registration) {
                window.dtrToast?.('Updates are not supported in this browser.', 'warning');
                return;
            }

            this.checkingUpdate = true;
            this.lastChecked = new Date().toISOString();

            try {
                await registration.update();
                refreshPwaStore();

                if (!this.updateAvailable) {
                    window.dtrToast?.('You are on the latest version.', 'success');
                }
            } catch {
                window.dtrToast?.('Unable to check for updates right now.', 'error');
            } finally {
                this.checkingUpdate = false;
            }
        },
        applyUpdate() {
            const waiting = registration?.waiting;
            if (!waiting) {
                return;
            }

            waiting.postMessage({ type: 'SKIP_WAITING' });
        },
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        refreshPwaStore();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        refreshPwaStore();
        window.dtrToast?.('BACS app installed successfully.', 'success');
    });

    window.addEventListener('online', () => {
        const store = Alpine.store('pwa');
        const wasOnline = store.online;
        store.online = true;
        refreshPwaStore();

        if (!wasOnline) {
            window.dtrToast?.('Connection restored.', 'success', 3500);
        }
    });

    window.addEventListener('offline', () => {
        const store = Alpine.store('pwa');
        store.online = false;
        refreshPwaStore();
        window.dtrToast?.('You are offline. Some BACS features require an internet connection.', 'warning', 5000);
    });

    window.addEventListener('bacs:pageshow', () => refreshPwaStore());

    registerServiceWorker();
}
