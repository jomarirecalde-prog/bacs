import './bootstrap';
import Alpine from 'alpinejs';
import QRCode from 'qrcode';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('manilaClock', () => ({
        dateLabel: '',
        timeLabel: '',
        offset: 0,
        async init() {
            try {
                const res = await fetch('/server-time', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.offset = data.timestamp - Date.now();
            } catch {
                this.offset = 0;
            }
            this.tick();
            setInterval(() => this.tick(), 1000);
        },
        tick() {
            const now = new Date(Date.now() + this.offset);
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
            await QRCode.toCanvas(this.$refs.canvas, this.token, {
                width: 320,
                margin: 1,
                color: { dark: '#0f172a', light: '#ffffff' },
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
});

Alpine.start();

window.dtrToast = function (message, type = 'success') {
    window.dispatchEvent(new CustomEvent('dtr-toast', { detail: { message, type } }));
};
