import './bootstrap';
import Alpine from 'alpinejs';
import QRCode from 'qrcode';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
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
            link.download = `${this.name.replace(/\s+/g, '-')}-qr.png`;
            link.click();
        },
    }));
});

Alpine.start();
