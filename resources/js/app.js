import './bootstrap';
import Alpine from 'alpinejs';
import { bootNavigation } from './navigation';

window.Alpine = Alpine;

const serverClock = {
    offset: 0,
    promise: null,
    async sync() {
        if (this.promise) {
            return this.promise;
        }

        this.promise = (async () => {
            try {
                const cached = sessionStorage.getItem('bacs-server-clock');
                if (cached) {
                    const parsed = JSON.parse(cached);
                    if (parsed.expires > Date.now()) {
                        this.offset = parsed.offset;
                        return this.offset;
                    }
                }
            } catch {
                // sessionStorage may be unavailable.
            }

            try {
                const res = await fetch('/server-time', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.offset = data.timestamp - Date.now();
                sessionStorage.setItem('bacs-server-clock', JSON.stringify({
                    offset: this.offset,
                    expires: Date.now() + 30000,
                }));
            } catch {
                this.offset = 0;
            }

            return this.offset;
        })();

        return this.promise;
    },
};

window.serverClock = serverClock;

function onPageHide(callback) {
    window.addEventListener('bacs:pagehide', callback, { once: true });
}

document.addEventListener('alpine:init', () => {
    Alpine.data('manilaClock', () => ({
        dateLabel: '',
        timeLabel: '',
        timer: null,
        async init() {
            await serverClock.sync();
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        tick() {
            const now = new Date(Date.now() + serverClock.offset);
            const opts = { timeZone: 'Asia/Manila' };
            this.dateLabel = now.toLocaleDateString('en-PH', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                ...opts,
            });
            this.timeLabel = now.toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                ...opts,
            });
        },
    }));

    Alpine.data('confirmAction', (message = 'Are you sure?') => ({
        open: false,
        message,
        pending: null,
        ask(callback) {
            this.pending = callback;
            this.open = true;
        },
        yes() {
            const fn = this.pending;
            this.open = false;
            this.pending = null;
            if (typeof fn === 'function') fn();
        },
    }));

    Alpine.data('qrCard', (token, name) => ({
        token,
        name,
        async init() {
            if (!this.$refs.canvas) return;
            const { default: QRCode } = await import('qrcode');
            await QRCode.toCanvas(this.$refs.canvas, this.token, {
                width: 320,
                margin: 1,
                color: { dark: '#064e3b', light: '#ffffff' },
            });
        },
        download() {
            const url = this.$refs.canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.href = url;
            link.download = `${String(this.name || 'employee').replace(/\s+/g, '-')}-qr.png`;
            link.click();
        },
    }));

    Alpine.data('notificationBell', (payload = {}) => ({
        open: false,
        unread: payload.unread || 0,
        items: payload.items || [],
        loaded: Array.isArray(payload.items) && payload.items.length > 0,
        loading: false,
        feedUrl: payload.feedUrl,
        readAllUrl: payload.readAllUrl,
        unlisten: null,
        init() {
            this.unlisten = window.listenToUser?.('.notification.received', (data) => this.receive(data));
            window.addEventListener('echo-connected', () => this.syncCount());
        },
        async toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) {
                await this.sync({ items: true });
            }
        },
        receive(data) {
            const note = data.notification;
            if (!note?.id) {
                return;
            }

            if (this.loaded) {
                this.items = [note, ...this.items.filter((item) => item.id !== note.id)].slice(0, 20);
            }
            this.unread = typeof data.unread === 'number' ? data.unread : this.unread + 1;

            if (data.toast !== false) {
                window.dtrToast(note.title, this.toastType(note.type), 5000);
            }
        },
        toastType(type) {
            if (type === 'warning') return 'warning';
            if (type === 'success') return 'success';
            if (type === 'error') return 'error';
            return 'info';
        },
        async syncCount() {
            await this.sync({ items: false });
        },
        async sync({ items = true } = {}) {
            if (!this.feedUrl || this.loading) {
                return;
            }

            this.loading = true;
            try {
                const { data } = await window.axios.get(this.feedUrl, {
                    params: { items: items ? 1 : 0 },
                });
                this.unread = data.unread ?? this.unread;
                if (items && data.items) {
                    this.items = data.items;
                    this.loaded = true;
                }
            } catch {
                // Offline users keep the last known list until the next reconnect.
            } finally {
                this.loading = false;
            }
        },
        async markRead(note, event) {
            if (!note.unread) {
                return;
            }

            note.unread = false;
            this.unread = Math.max(0, this.unread - 1);

            try {
                await window.axios.post(`/notifications/${note.id}/read`, {}, {
                    headers: { Accept: 'application/json' },
                });
            } catch {
                note.unread = true;
                this.unread += 1;
                event?.preventDefault();
            }
        },
        async markAllRead() {
            const previous = this.items;
            this.items = this.items.map((item) => ({ ...item, unread: false }));
            this.unread = 0;

            try {
                await window.axios.post(this.readAllUrl, {}, {
                    headers: { Accept: 'application/json' },
                });
            } catch {
                this.items = previous;
            }
        },
    }));

    Alpine.data('calendarLive', (payload = {}) => ({
        events: payload.events || {},
        eventCount: payload.eventCount || 0,
        view: payload.view,
        focus: payload.focus,
        start: payload.start,
        end: payload.end,
        liveUrl: payload.liveUrl,
        type: payload.type || '',
        selected: null,
        open: false,
        refreshing: false,
        unlisten: null,
        controller: null,
        init() {
            this.unlisten = window.listenToUser?.('.calendar.changed', (data) => this.onChanged(data));
            onPageHide(() => this.destroy());
        },
        destroy() {
            this.unlisten?.();
            this.controller?.abort();
        },
        show(id) {
            this.selected = this.events[id] ?? null;
            this.open = this.selected !== null;
        },
        close() {
            this.open = false;
        },
        onChanged(data) {
            const from = data.start_date;
            const to = data.end_date;
            if (from && to && (to < this.start || from > this.end)) {
                return;
            }

            this.refresh();
        },
        async refresh() {
            if (!this.liveUrl || this.refreshing) {
                return;
            }

            this.refreshing = true;
            this.controller?.abort();
            this.controller = new AbortController();

            try {
                const { data } = await window.axios.get(this.liveUrl, {
                    params: { view: this.view, date: this.focus, type: this.type || undefined },
                    headers: { Accept: 'application/json' },
                    signal: this.controller.signal,
                });

                this.events = data.events || this.events;
                this.eventCount = data.eventCount ?? this.eventCount;
                if (data.start) this.start = data.start;
                if (data.end) this.end = data.end;

                if (data.html && this.$refs.body) {
                    this.$refs.body.innerHTML = data.html;
                    window.Alpine.initTree(this.$refs.body);
                }
            } catch (error) {
                if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED') {
                    return;
                }
                // Keep the last rendered grid; the next event or reconnect retries.
            } finally {
                this.refreshing = false;
            }
        },
    }));

    Alpine.data('adminLive', (payload = {}) => ({
        liveUrl: payload.liveUrl,
        summary: payload.summary || {},
        departments: payload.departments || [],
        rows: payload.rows || [],
        timer: null,
        controller: null,
        onVisibility: null,
        init() {
            this.onVisibility = () => {
                if (!document.hidden) {
                    this.refresh();
                }
            };
            this.timer = setInterval(() => this.refresh(), 15000);
            document.addEventListener('visibilitychange', this.onVisibility);
            onPageHide(() => this.destroy());
        },
        destroy() {
            clearInterval(this.timer);
            this.controller?.abort();
            document.removeEventListener('visibilitychange', this.onVisibility);
        },
        async refresh() {
            if (document.hidden || !this.liveUrl) {
                return;
            }

            this.controller?.abort();
            this.controller = new AbortController();

            try {
                const params = new URLSearchParams(window.location.search);
                const { data } = await window.axios.get(this.liveUrl, {
                    params: Object.fromEntries(params.entries()),
                    signal: this.controller.signal,
                });

                if (data.summary) {
                    this.summary = data.summary;
                    Object.entries(data.summary).forEach(([key, value]) => {
                        document.querySelectorAll(`[data-live-stat="${key}"]`).forEach((el) => {
                            el.textContent = value;
                        });
                    });
                }

                if (Array.isArray(data.departments)) {
                    this.departments = data.departments;
                    this.renderDepartments(data.departments);
                }
            } catch (error) {
                if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED') {
                    return;
                }
            }
        },
        renderDepartments(departments) {
            const body = document.getElementById('live-dept-body');
            if (!body) {
                return;
            }

            if (!departments.length) {
                return;
            }

            body.innerHTML = departments.map((dept) => {
                const headcount = Math.max(1, Number(dept.employees) || 1);
                const rate = Math.round((Number(dept.present) / headcount) * 100);
                const fill = rate >= 75 ? 'meter-fill' : (rate >= 50 ? 'meter-fill-warn' : 'meter-fill-critical');

                return `<tr>
                    <td class="font-semibold text-ink">${escapeHtml(dept.department)}</td>
                    <td class="text-right tabular-nums">${dept.employees}</td>
                    <td class="text-right font-semibold text-brand-700 tabular-nums">${dept.present}</td>
                    <td class="text-right font-semibold text-warn-700 tabular-nums">${dept.late}</td>
                    <td class="text-right font-semibold text-critical-600 tabular-nums">${dept.absent}</td>
                    <td class="text-right font-semibold text-info-700 tabular-nums">${dept.working}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="meter"><div class="${fill}" style="width: ${Math.min(100, rate)}%"></div></div>
                            <span class="w-9 shrink-0 text-right text-xs font-bold text-muted tabular-nums">${rate}%</span>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        },
    }));

    Alpine.data('clockPanel', (payload = {}) => ({
        dateLabel: '',
        timeLabel: '',
        dialog: false,
        dialogTitle: '',
        dialogBody: '',
        pendingUrl: null,
        timeInUrl: payload.timeInUrl,
        timeOutUrl: payload.timeOutUrl,
        canTimeIn: payload.canTimeIn,
        canTimeOut: payload.canTimeOut,
        timer: null,
        async init() {
            await serverClock.sync();
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
            onPageHide(() => clearInterval(this.timer));
        },
        tick() {
            const now = new Date(Date.now() + serverClock.offset);
            const opts = { timeZone: 'Asia/Manila' };
            this.dateLabel = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', ...opts });
            this.timeLabel = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, ...opts });
        },
        confirmIn() {
            this.dialogTitle = 'Confirm Time In';
            this.dialogBody = 'Record your Time In using the server timestamp?';
            this.pendingUrl = this.timeInUrl;
            this.dialog = true;
        },
        confirmOut() {
            this.dialogTitle = 'Confirm Time Out';
            this.dialogBody = 'Record your Time Out using the server timestamp?';
            this.pendingUrl = this.timeOutUrl;
            this.dialog = true;
        },
        async submitPending() {
            this.dialog = false;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const res = await fetch(this.pendingUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!res.ok) {
                    window.dtrToast(data.message || Object.values(data.errors || { e: ['Unable to save'] })[0][0], 'error');
                    return;
                }
                window.dtrToast(data.message, 'success');
                this.applyAttendance(data.attendance);
            } catch {
                window.dtrToast('Unable to record attendance.', 'error');
            }
        },
        applyAttendance(attendance) {
            if (!attendance) {
                return;
            }
            const timeIn = document.getElementById('time-in-label');
            const timeOut = document.getElementById('time-out-label');
            const btnIn = document.getElementById('btn-in');
            const btnOut = document.getElementById('btn-out');
            if (timeIn && attendance.time_in) timeIn.textContent = attendance.time_in;
            if (timeOut && attendance.time_out) timeOut.textContent = attendance.time_out;
            this.canTimeIn = Boolean(attendance.can_time_in);
            this.canTimeOut = Boolean(attendance.can_time_out);
            if (btnIn) btnIn.disabled = !this.canTimeIn;
            if (btnOut) btnOut.disabled = !this.canTimeOut;
        },
    }));
});

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

Alpine.start();
bootNavigation();

window.dtrToast = function (message, type = 'success', duration = 3500) {
    window.dispatchEvent(new CustomEvent('dtr-toast', { detail: { message, type, duration } }));
};
