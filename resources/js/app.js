import './bootstrap';
import Alpine from 'alpinejs';
import { bootNavigation } from './navigation';
import { bootResponsive } from './responsive';
import { bootPwa } from './pwa';

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
                const res = await fetch(window.appUrl('/server-time'), { headers: { Accept: 'application/json' } });
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
    bootPwa(Alpine);

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
                await window.axios.post(window.appUrl(`/notifications/${note.id}/read`), {}, {
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
        echoTimer: null,
        echoOff: null,
        controller: null,
        onVisibility: null,
        init() {
            this.onVisibility = () => {
                if (!document.hidden) {
                    this.refresh();
                }
            };
            this.timer = setInterval(() => this.refresh(), 30000);
            document.addEventListener('visibilitychange', this.onVisibility);
            if (typeof window.listenToAttendanceDashboard === 'function') {
                this.echoOff = window.listenToAttendanceDashboard('.attendance.recorded', () => {
                    clearTimeout(this.echoTimer);
                    this.echoTimer = setTimeout(() => this.refresh(), 350);
                });
            }
            onPageHide(() => this.destroy());
        },
        destroy() {
            clearInterval(this.timer);
            clearTimeout(this.echoTimer);
            this.echoOff?.();
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

    Alpine.data('leaveBalanceAdjust', (payload = {}) => ({
        previewUrl: payload.previewUrl,
        leaveTypeCode: 'vacation',
        adjustmentKind: 'add',
        days: '',
        preview: null,
        loading: false,
        clearPreview() {
            this.preview = null;
        },
        formatDays(value) {
            if (value === null || value === undefined) return '—';
            return String(Number(value).toFixed(1)).replace(/\.0$/, '');
        },
        formatAdjustment(value) {
            if (value === null || value === undefined) return '—';
            const num = Number(value);
            const label = Math.abs(num).toFixed(1).replace(/\.0$/, '');
            return num > 0 ? `+${label} days` : (num < 0 ? `-${label} days` : '0 days');
        },
        async loadPreview() {
            if (!this.previewUrl || this.days === '' || Number(this.days) < 0) {
                this.preview = null;
                return;
            }
            this.loading = true;
            try {
                const { data } = await window.axios.post(this.previewUrl, {
                    leave_type_code: this.leaveTypeCode,
                    adjustment_kind: this.adjustmentKind,
                    days: this.days,
                });
                this.preview = data;
            } catch {
                this.preview = null;
                window.dtrToast('Unable to preview adjustment.', 'error');
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('leaveApply', (payload = {}) => ({
        previewUrl: payload.previewUrl,
        start: payload.start || '',
        end: payload.end || '',
        type: payload.type || 'vacation',
        special: payload.special || '',
        reason: payload.reason || '',
        days: null,
        async init() {
            await this.refreshDays();
        },
        get daysLabel() {
            if (this.days === null) return '—';
            return `${this.days} day${this.days === 1 ? '' : 's'}`;
        },
        async refreshDays() {
            if (!this.previewUrl || !this.start || !this.end || this.end < this.start) {
                this.days = null;
                return;
            }
            try {
                const { data } = await window.axios.get(this.previewUrl, {
                    params: {
                        start_date: this.start,
                        end_date: this.end,
                        leave_type: this.type,
                        special_leave_type: this.special || undefined,
                    },
                });
                this.days = data.days;
            } catch {
                this.days = null;
            }
        },
        beforeSubmit(event) {
            const input = event.target.querySelector('input[name="employee_signature"]');
            if (input && !input.value) {
                event.preventDefault();
                window.dtrToast('Please sign the leave application before submitting.', 'error');
            }
        },
    }));

    Alpine.data('signaturePad', () => ({
        drawing: false,
        init() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#10201b';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            const point = (event) => {
                const rect = canvas.getBoundingClientRect();
                const source = event.touches ? event.touches[0] : event;
                return {
                    x: (source.clientX - rect.left) * (canvas.width / rect.width),
                    y: (source.clientY - rect.top) * (canvas.height / rect.height),
                };
            };
            const start = (event) => {
                this.drawing = true;
                const p = point(event);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                event.preventDefault();
            };
            const move = (event) => {
                if (!this.drawing) return;
                const p = point(event);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                event.preventDefault();
            };
            const end = () => {
                if (!this.drawing) return;
                this.drawing = false;
                if (this.$refs.input) {
                    this.$refs.input.value = canvas.toDataURL('image/png');
                }
            };
            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', end);
            const form = canvas.closest('form');
            form?.addEventListener('submit', () => {
                if (this.$refs.input && !this.$refs.input.value) {
                    this.$refs.input.value = canvas.toDataURL('image/png');
                }
            });
            onPageHide(() => window.removeEventListener('mouseup', end));
        },
        clear() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
            if (this.$refs.input) this.$refs.input.value = '';
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
        nextActionLabel: payload.nextActionLabel || '',
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
            this.dialogTitle = 'Confirm Attendance';
            this.dialogBody = this.nextActionLabel
                ? `Record ${this.nextActionLabel} using the server timestamp?`
                : 'Record your next attendance action using the server timestamp?';
            this.pendingUrl = this.timeInUrl;
            this.dialog = true;
        },
        confirmOut() {
            this.confirmIn();
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
            const mapping = {
                am_time_in: attendance.am_time_in,
                am_time_out: attendance.am_time_out,
                pm_time_in: attendance.pm_time_in,
                pm_time_out: attendance.pm_time_out,
                overtime: attendance.overtime,
            };
            Object.entries(mapping).forEach(([key, value]) => {
                const el = document.querySelector(`[data-punch="${key}"]`);
                if (el && value) el.textContent = value;
            });
            const timeIn = document.getElementById('time-in-label');
            const timeOut = document.getElementById('time-out-label');
            const nextAction = document.getElementById('next-action-label');
            const btnIn = document.getElementById('btn-in');
            if (timeIn && attendance.am_time_in) timeIn.textContent = attendance.am_time_in;
            if (timeOut && attendance.pm_time_out) timeOut.textContent = attendance.pm_time_out;
            if (nextAction && attendance.next_action_label) nextAction.textContent = attendance.next_action_label;
            this.nextActionLabel = attendance.next_action_label || this.nextActionLabel;
            this.canTimeIn = Boolean(attendance.can_record ?? attendance.can_time_in);
            this.canTimeOut = Boolean(attendance.can_record ?? attendance.can_time_out);
            if (btnIn) btnIn.disabled = !this.canTimeIn;
        },
    }));

    Alpine.data('profilePage', (payload = {}) => ({
        updateUrl: payload.updateUrl,
        photoUploadUrl: payload.photoUploadUrl,
        photoRemoveUrl: payload.photoRemoveUrl,
        passwordUrl: payload.passwordUrl,
        profile: payload.profile || {},
        editing: false,
        saving: false,
        uploadingPhoto: false,
        changingPassword: false,
        errors: {},
        passwordErrors: {},
        form: {},
        passwordForm: {
            current_password: '',
            password: '',
            password_confirmation: '',
        },
        strengthPercent: 0,
        strengthLabel: 'Enter a new password',
        strengthClass: 'bg-surface-200',
        avatarUrl: payload.profile?.employee?.photo_url || '',
        displayName: payload.profile?.employee?.full_name || payload.profile?.user?.name || '',
        subtitle: payload.profile?.employee
            ? `${payload.profile.employee.position || ''}${payload.profile.employee.department ? ' · ' + payload.profile.employee.department : ''}`
            : '',
        employeeNumber: payload.profile?.employee?.employee_number || '',
        hasPhoto: Boolean(payload.profile?.employee?.has_photo),
        passwordChangedLabel: payload.profile?.user?.password_changed_at
            ? new Date(payload.profile.user.password_changed_at).toLocaleString()
            : '—',
        init() {
            this.resetForm();
            if (window.location.hash === '#password' || payload.mustChangePassword) {
                this.$nextTick(() => document.getElementById('password')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        },
        get readOnlyPersonal() {
            const e = this.profile.employee || {};
            return [
                { label: 'First name', value: e.first_name },
                { label: 'Middle name', value: e.middle_name },
                { label: 'Last name', value: e.last_name },
                { label: 'Suffix', value: e.suffix },
                { label: 'Email', value: e.email },
                { label: 'Contact number', value: e.contact_number },
                { label: 'Address', value: e.address },
                { label: 'Birth date', value: e.birth_date ? new Date(e.birth_date + 'T00:00:00').toLocaleDateString() : null },
            ];
        },
        resetForm() {
            const e = this.profile.employee || {};
            this.form = {
                first_name: e.first_name || '',
                middle_name: e.middle_name || '',
                last_name: e.last_name || '',
                suffix: e.suffix || '',
                email: e.email || this.profile.user?.email || '',
                contact_number: e.contact_number || '',
                address: e.address || '',
                birth_date: e.birth_date || '',
            };
        },
        startEdit() {
            this.errors = {};
            this.resetForm();
            this.editing = true;
        },
        cancelEdit() {
            this.editing = false;
            this.errors = {};
            this.resetForm();
        },
        applyProfile(profile) {
            this.profile = profile;
            this.avatarUrl = profile.employee?.photo_url || this.avatarUrl;
            this.displayName = profile.employee?.full_name || profile.user?.name || this.displayName;
            this.subtitle = profile.employee
                ? `${profile.employee.position || ''}${profile.employee.department ? ' · ' + profile.employee.department : ''}`
                : this.subtitle;
            this.employeeNumber = profile.employee?.employee_number || this.employeeNumber;
            this.hasPhoto = Boolean(profile.employee?.has_photo);
            this.resetForm();
            this.syncHeader(profile);
        },
        syncHeader(profile) {
            const nameEl = document.getElementById('header-user-name');
            if (nameEl) {
                nameEl.textContent = profile.employee?.full_name || profile.user?.name || nameEl.textContent;
            }
            const avatar = document.getElementById('header-avatar');
            const fallback = document.getElementById('header-avatar-fallback');
            const url = profile.employee?.photo_url;
            if (avatar && url && profile.employee?.has_photo) {
                avatar.src = url;
                avatar.classList.remove('hidden');
                fallback?.classList.add('hidden');
            }
        },
        async saveProfile() {
            this.saving = true;
            this.errors = {};
            try {
                const { data } = await window.axios.put(this.updateUrl, this.form, {
                    headers: { Accept: 'application/json' },
                });
                this.applyProfile(data.profile);
                this.editing = false;
                window.dtrToast(data.message || 'Profile updated.', 'success');
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors
                        ? Object.fromEntries(Object.entries(error.response.data.errors).map(([k, v]) => [k, v[0]]))
                        : {};
                }
                window.dtrToast(error.response?.data?.message || 'Could not save profile.', 'error');
            } finally {
                this.saving = false;
            }
        },
        async uploadPhoto(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;

            this.uploadingPhoto = true;
            const body = new FormData();
            body.append('photo', file);

            try {
                const { data } = await window.axios.post(this.photoUploadUrl, body, {
                    headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' },
                });
                this.applyProfile(data.profile);
                window.dtrToast(data.message || 'Photo updated.', 'success');
            } catch (error) {
                const message = error.response?.data?.message
                    || error.response?.data?.errors?.photo?.[0]
                    || 'Could not upload photo.';
                window.dtrToast(message, 'error');
            } finally {
                this.uploadingPhoto = false;
            }
        },
        async removePhoto() {
            if (!confirm('Remove your profile picture?')) return;

            try {
                const { data } = await window.axios.delete(this.photoRemoveUrl, {
                    headers: { Accept: 'application/json' },
                });
                this.applyProfile(data.profile);
                window.dtrToast(data.message || 'Photo removed.', 'success');
            } catch (error) {
                window.dtrToast('Could not remove photo.', 'error');
            }
        },
        updateStrength() {
            const value = this.passwordForm.password || '';
            let score = 0;
            if (value.length >= 8) score += 25;
            if (value.length >= 12) score += 15;
            if (/[A-Z]/.test(value)) score += 20;
            if (/[0-9]/.test(value)) score += 20;
            if (/[^A-Za-z0-9]/.test(value)) score += 20;
            this.strengthPercent = Math.min(100, score);
            if (!value) {
                this.strengthLabel = 'Enter a new password';
                this.strengthClass = 'bg-surface-200';
            } else if (score < 40) {
                this.strengthLabel = 'Weak';
                this.strengthClass = 'bg-critical-500';
            } else if (score < 70) {
                this.strengthLabel = 'Fair';
                this.strengthClass = 'bg-warn-400';
            } else {
                this.strengthLabel = 'Strong';
                this.strengthClass = 'bg-brand-500';
            }
        },
        async changePassword() {
            this.changingPassword = true;
            this.passwordErrors = {};
            try {
                const { data } = await window.axios.put(this.passwordUrl, this.passwordForm, {
                    headers: { Accept: 'application/json' },
                });
                this.passwordForm = { current_password: '', password: '', password_confirmation: '' };
                this.strengthPercent = 0;
                this.strengthLabel = 'Enter a new password';
                if (data.password_changed_at) {
                    this.passwordChangedLabel = new Date(data.password_changed_at).toLocaleString();
                }
                window.dtrToast(data.message || 'Password updated.', 'success');
            } catch (error) {
                if (error.response?.status === 422) {
                    this.passwordErrors = error.response.data.errors
                        ? Object.fromEntries(Object.entries(error.response.data.errors).map(([k, v]) => [k, v[0]]))
                        : {};
                }
                window.dtrToast(error.response?.data?.message || 'Could not update password.', 'error');
            } finally {
                this.changingPassword = false;
            }
        },
    }));

    Alpine.data('approverPicker', ({ name, multiple, selected, searchUrl }) => ({
        name,
        multiple,
        selected: [...(selected || [])],
        query: '',
        results: [],
        open: false,
        inputName() {
            return `${this.name}[]`;
        },
        async search() {
            if (this.query.trim().length < 2) {
                this.results = [];
                return;
            }
            const res = await fetch(`${searchUrl}?q=${encodeURIComponent(this.query)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            this.results = (data.results || []).filter((r) => !this.selected.some((s) => s.id === r.id));
            this.open = true;
        },
        add(person) {
            if (!this.multiple) {
                this.selected = [person];
            } else if (!this.selected.some((s) => s.id === person.id)) {
                this.selected.push(person);
            }
            this.query = '';
            this.results = [];
            this.open = false;
        },
        remove(id) {
            this.selected = this.selected.filter((s) => s.id !== id);
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
bootResponsive();

window.dtrToast = function (message, type = 'success', duration = 3500) {
    window.dispatchEvent(new CustomEvent('dtr-toast', { detail: { message, type, duration } }));
};
