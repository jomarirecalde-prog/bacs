/**
 * Client-side navigation for the authenticated app shell.
 *
 * Sidebar, header, Echo, and the notification bell stay mounted. Only the
 * main column is replaced, previous page timers are cleared, and in-flight
 * navigations are aborted when the user clicks another link.
 */
import { closeMobileSidebar } from './responsive';
import { applyCsrfToken, handleSessionExpired } from './session';

const PARTIAL_HEADER = { 'X-BACS-Partial': '1', Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };

let inflight = null;

function progressEl() {
    return document.getElementById('nav-progress');
}

function setProgress(on) {
    const bar = progressEl();
    if (!bar) {
        return;
    }
    bar.hidden = !on;
    bar.classList.toggle('is-active', on);
}

function sameOrigin(url) {
    try {
        return new URL(url, window.location.origin).origin === window.location.origin;
    } catch {
        return false;
    }
}

function shouldInterceptClick(event, anchor) {
    if (!document.getElementById('app-main')) {
        return false;
    }
    if (document.body.classList.contains('landing-page')) {
        return false;
    }
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }
    if (anchor.target && anchor.target !== '_self') {
        return false;
    }
    if (anchor.hasAttribute('download') || anchor.dataset.fullReload === '1') {
        return false;
    }
    const url = new URL(anchor.href, window.location.origin);
    if (url.origin !== window.location.origin) {
        return false;
    }
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
        if (url.hash) {
            return false;
        }
    }
    const dest = url.pathname;
    if (dest.startsWith('/attendance-station') || dest.startsWith('/login')) {
        return false;
    }
    return true;
}

function updateNav(url) {
    const path = new URL(url, window.location.origin).pathname;
    const links = document.querySelectorAll('aside a.nav-link[href]');
    let best = null;
    let bestLen = -1;

    links.forEach((link) => {
        const href = new URL(link.href, window.location.origin).pathname;
        if (path === href || (href !== '/' && path.startsWith(`${href}/`))) {
            if (href.length > bestLen) {
                best = link;
                bestLen = href.length;
            }
        }
    });

    links.forEach((link) => {
        const on = link === best;
        link.classList.toggle('nav-link-active', on);
        link.classList.toggle('nav-link-idle', !on);
        const svg = link.querySelector('svg');
        if (svg) {
            svg.classList.toggle('text-gold-300', on);
        }
    });
}

function teardownPage() {
    window.dispatchEvent(new CustomEvent('bacs:pagehide'));
}

function mountPartial(html, url) {
    const parsed = new DOMParser().parseFromString(html, 'text/html');
    const fragment = parsed.getElementById('bacs-partial');
    const main = document.getElementById('app-main');

    if (!fragment || !main) {
        window.location.assign(url);
        return;
    }

    teardownPage();
    const nodes = Array.from(fragment.childNodes).map((node) => node.cloneNode(true));
    main.replaceChildren(...nodes);

    const title = fragment.dataset.title;
    if (title) {
        document.title = title;
    }
    const heading = document.getElementById('page-heading');
    const sub = document.getElementById('page-subheading');
    if (heading && fragment.dataset.pageTitle) {
        heading.textContent = fragment.dataset.pageTitle;
    }
    if (sub && fragment.dataset.pageSubtitle) {
        sub.textContent = fragment.dataset.pageSubtitle;
    }

    const back = document.getElementById('page-back');
    if (back) {
        const show = fragment.dataset.backShow !== '0';
        back.hidden = !show;
        back.classList.toggle('hidden', !show);
        const backUrl = (fragment.dataset.backUrl || '').trim();
        if (backUrl) {
            back.dataset.backUrl = backUrl;
        } else {
            delete back.dataset.backUrl;
        }
        const backFallback = (fragment.dataset.backFallback || '').trim();
        if (backFallback) {
            back.dataset.backFallback = backFallback;
        }
        const backLabel = (fragment.dataset.backLabel || 'Back').trim() || 'Back';
        back.setAttribute('aria-label', backLabel);
        back.setAttribute('title', backLabel);
    }

    updateNav(url);
    activateScripts(main);

    if (window.Alpine?.initTree) {
        window.Alpine.initTree(main);
    }

    window.dispatchEvent(new CustomEvent('bacs:pageshow', { detail: { url } }));
    window.scrollTo(0, 0);
}

function activateScripts(root) {
    root.querySelectorAll('script').forEach((old) => {
        const script = document.createElement('script');
        [...old.attributes].forEach((attr) => script.setAttribute(attr.name, attr.value));
        script.textContent = old.textContent;
        old.replaceWith(script);
    });
}

export async function navigate(url, { replace = false, push = true } = {}) {
    const target = new URL(url, window.location.origin).href;

    if (inflight) {
        inflight.abort();
    }

    const controller = new AbortController();
    inflight = controller;
    setProgress(true);

    try {
        const response = await fetch(target, {
            headers: PARTIAL_HEADER,
            credentials: 'same-origin',
            signal: controller.signal,
        });

        const csrfHeader = response.headers.get('X-CSRF-TOKEN');
        if (csrfHeader) {
            applyCsrfToken(csrfHeader);
        }

        if (response.status === 419) {
            handleSessionExpired();
            return;
        }

        if (response.redirected && (response.url.includes('/login') || response.status === 401)) {
            window.location.assign(response.url);
            return;
        }

        if (!response.ok) {
            window.location.assign(target);
            return;
        }

        const html = await response.text();
        const type = response.headers.get('content-type') || '';
        if (!type.includes('text/html')) {
            window.location.assign(target);
            return;
        }

        if (controller.signal.aborted) {
            return;
        }

        mountPartial(html, response.url || target);
        closeMobileSidebar();

        if (push) {
            const method = replace ? 'replaceState' : 'pushState';
            window.history[method]({ spa: true }, '', response.url || target);
        }
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }
        window.location.assign(target);
    } finally {
        if (inflight === controller) {
            inflight = null;
            setProgress(false);
        }
    }
}

function samePath(left, right) {
    try {
        const a = new URL(left, window.location.origin);
        const b = new URL(right, window.location.origin);
        return a.pathname === b.pathname && a.search === b.search;
    } catch {
        return false;
    }
}

function handlePageBackClick(event) {
    const button = event.target.closest('#page-back');
    if (!button) {
        return false;
    }
    event.preventDefault();
    const explicit = (button.dataset.backUrl || '').trim();
    if (explicit) {
        navigate(explicit);
        return true;
    }
    const fallback = (button.dataset.backFallback || '').trim();
    if (fallback && !samePath(fallback, window.location.href)) {
        navigate(fallback);
        return true;
    }
    if (window.history.state?.spa) {
        window.history.back();
        return true;
    }
    if (window.history.length > 1) {
        window.history.back();
    }
    return true;
}

function onClick(event) {
    if (handlePageBackClick(event)) {
        return;
    }
    const anchor = event.target.closest('a[href]');
    if (!anchor || !shouldInterceptClick(event, anchor)) {
        return;
    }
    event.preventDefault();
    navigate(anchor.href);
}

function onSubmit(event) {
    if (!document.getElementById('app-main') || document.body.classList.contains('landing-page')) {
        return;
    }
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (form.method.toLowerCase() !== 'get') {
        return;
    }
    if (form.hasAttribute('data-full-reload') || form.target === '_blank') {
        return;
    }
    const action = form.getAttribute('action') || window.location.href;
    if (!sameOrigin(action)) {
        return;
    }
    event.preventDefault();
    const url = new URL(action, window.location.origin);
    url.search = new URLSearchParams(new FormData(form)).toString();
    navigate(url.href);
}

export function bootNavigation() {
    document.addEventListener('click', onClick);
    document.addEventListener('submit', onSubmit);
    window.addEventListener('popstate', () => {
        if (!document.getElementById('app-main') || document.body.classList.contains('landing-page')) {
            return;
        }
        navigate(window.location.href, { push: false });
    });
}
