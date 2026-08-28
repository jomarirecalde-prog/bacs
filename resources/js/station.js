import './bootstrap';
import Alpine from 'alpinejs';
import jsQR from 'jsqr';

window.Alpine = Alpine;

function toneFor(code) {
    if (['AM_TIME_IN', 'AM_TIME_OUT', 'PM_TIME_IN', 'PM_TIME_OUT', 'OVERTIME'].includes(code)) return 'text-brand-300';
    if (['DUPLICATE_SCAN', 'ATTENDANCE_COMPLETED'].includes(code)) return 'text-warn-300';
    return 'text-critical-300';
}

document.addEventListener('alpine:init', () => {
    Alpine.data('stationKiosk', (config) => ({
        scanUrl: config.scanUrl,
        heartbeatUrl: config.heartbeatUrl,
        csrf: config.csrf,
        locked: config.locked,
        dateLabel: '',
        timeLabel: '',
        offset: 0,
        cameraStatus: 'Starting camera…',
        busy: false,
        result: null,
        stream: null,
        detector: null,
        lastScan: '',
        lastScanAt: 0,
        raf: null,
        resultTimer: null,
        async init() {
            this.tick();
            setInterval(() => this.tick(), 1000);
            setInterval(() => this.heartbeat(), 45000);
            await this.startCamera();
            this.heartbeat();
        },
        tick() {
            const now = new Date(Date.now() + this.offset);
            const opts = { timeZone: 'Asia/Manila' };
            this.dateLabel = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', ...opts });
            this.timeLabel = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, ...opts });
        },
        async heartbeat() {
            try {
                const res = await fetch(this.heartbeatUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.status === 401) {
                    window.location.href = '/attendance-station/login';
                    return;
                }
                const data = await res.json();
                this.locked = Boolean(data.locked);
                if (data.server_time) {
                    this.offset = new Date(data.server_time).getTime() - Date.now();
                }
            } catch {
                // Keep the kiosk UI available; the next successful heartbeat will refresh status.
            }
        },
        async startCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                if ('BarcodeDetector' in window) {
                    const formats = await window.BarcodeDetector.getSupportedFormats?.() || ['qr_code'];
                    if (formats.includes('qr_code')) {
                        this.detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                    }
                }
                this.cameraStatus = 'Point the camera at the employee QR code.';
                this.scanLoop();
            } catch {
                this.cameraStatus = 'Camera access is required. Allow camera permission and reload.';
            }
        },
        async scanLoop() {
            const video = this.$refs.video;
            if (!video || video.readyState < 2) {
                this.raf = requestAnimationFrame(() => this.scanLoop());
                return;
            }
            try {
                let value = null;
                if (this.detector) {
                    const codes = await this.detector.detect(video);
                    value = codes[0]?.rawValue || null;
                } else {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    ctx.drawImage(video, 0, 0);
                    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    value = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' })?.data || null;
                }
                if (value) {
                    await this.onCode(value);
                }
            } catch {
                // Ignore a single failed frame and keep scanning.
            }
            this.raf = requestAnimationFrame(() => this.scanLoop());
        },
        async onCode(value) {
            const now = Date.now();
            if (this.busy || this.locked) return;
            if (value === this.lastScan && now - this.lastScanAt < 2500) return;
            this.lastScan = value;
            this.lastScanAt = now;
            this.busy = true;
            try {
                const res = await fetch(this.scanUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ token: value }),
                });
                const data = await res.json();
                this.showResult(data);
            } catch {
                this.showResult({
                    code: 'ERROR',
                    title: 'Scan Failed',
                    message: 'Unable to reach the server. Attendance was not recorded.',
                });
            } finally {
                this.busy = false;
            }
        },
        showResult(data) {
            this.result = {
                code: data.code,
                codeLabel: data.action_label || data.code || 'SCAN',
                title: data.title || 'Scan Result',
                message: data.message || '',
                name: data.employee?.name || '',
                employeeNumber: data.employee?.employee_number || '',
                department: data.employee?.department || '',
                position: data.employee?.position || '',
                photo: data.employee?.photo || '',
                action: data.action_label || '',
                nextAction: data.next_action_label || '',
                time: data.time || '',
                date: data.date || '',
                progress: data.attendance?.progress || [],
                status: data.attendance?.attendance_status || data.attendance?.status || '',
            };
            this.resultTone = toneFor(data.code);
            clearTimeout(this.resultTimer);
            this.resultTimer = setTimeout(() => { this.result = null; }, 6000);
        },
        resultTone: 'text-brand-300',
    }));
});

Alpine.start();
