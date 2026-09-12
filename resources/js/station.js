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
        facingMode: 'environment',
        switchingCamera: false,
        cameraNeedsTap: false,
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
            await this.$nextTick();
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
        stopCamera() {
            if (this.raf) {
                cancelAnimationFrame(this.raf);
                this.raf = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }
            this.cameraNeedsTap = false;
        },
        prepareVideoElement(video) {
            video.setAttribute('playsinline', '');
            video.setAttribute('webkit-playsinline', '');
            video.muted = true;
            video.playsInline = true;
            video.autoplay = true;
        },
        videoConstraints(level = 0) {
            const presets = [
                { facingMode: { ideal: this.facingMode }, width: { ideal: 1280 }, height: { ideal: 720 } },
                { facingMode: { ideal: this.facingMode } },
                { facingMode: this.facingMode },
                true,
            ];
            return presets[Math.min(level, presets.length - 1)];
        },
        async acquireStream() {
            if (!navigator.mediaDevices?.getUserMedia) {
                throw new Error('unsupported');
            }
            let lastError = null;
            for (let level = 0; level < 4; level += 1) {
                try {
                    return await navigator.mediaDevices.getUserMedia({
                        video: this.videoConstraints(level),
                        audio: false,
                    });
                } catch (error) {
                    lastError = error;
                    if (error?.name === 'NotAllowedError' || error?.name === 'PermissionDeniedError') {
                        throw error;
                    }
                }
            }
            throw lastError || new Error('Could not open camera');
        },
        waitForVideoMetadata(video) {
            return new Promise((resolve) => {
                if (video.readyState >= HTMLMediaElement.HAVE_METADATA) {
                    resolve();
                    return;
                }
                const onReady = () => {
                    video.removeEventListener('loadedmetadata', onReady);
                    resolve();
                };
                video.addEventListener('loadedmetadata', onReady, { once: true });
                setTimeout(resolve, 2500);
            });
        },
        async playVideo(video) {
            try {
                await video.play();
                return !video.paused;
            } catch {
                return false;
            }
        },
        async setupDetector() {
            this.detector = null;
            if (!('BarcodeDetector' in window)) {
                return;
            }
            const formats = await window.BarcodeDetector.getSupportedFormats?.() || ['qr_code'];
            if (formats.includes('qr_code')) {
                this.detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            }
        },
        activeCameraStatus() {
            return this.facingMode === 'user'
                ? 'Front camera active. Point at the employee QR code.'
                : 'Point the camera at the employee QR code.';
        },
        async startCamera(fromUserGesture = false) {
            this.cameraNeedsTap = false;
            const video = this.$refs.video;
            if (!video) {
                this.cameraStatus = 'Camera preview unavailable. Reload the page.';
                return false;
            }

            try {
                this.prepareVideoElement(video);
                this.stream = await this.acquireStream();
                video.srcObject = this.stream;
                await this.waitForVideoMetadata(video);

                let playing = await this.playVideo(video);
                if (!playing) {
                    playing = await this.playVideo(video);
                }

                if (!playing && !fromUserGesture) {
                    this.cameraNeedsTap = true;
                    this.cameraStatus = 'Tap Start Camera to enable the preview on this device.';
                    return false;
                }

                await this.setupDetector();
                this.cameraStatus = this.activeCameraStatus();
                this.scanLoop();
                return true;
            } catch (error) {
                if (error?.message === 'unsupported') {
                    this.cameraStatus = 'Camera is not available here. Open the station in Safari over HTTPS.';
                } else if (!fromUserGesture) {
                    this.cameraNeedsTap = true;
                    this.cameraStatus = 'Tap Start Camera after allowing camera access in your browser settings.';
                } else {
                    this.cameraStatus = 'Camera access is required. Allow camera permission and reload.';
                }
                return false;
            }
        },
        async requestCameraStart() {
            this.cameraStatus = 'Starting camera…';
            const video = this.$refs.video;
            if (this.stream && video) {
                this.prepareVideoElement(video);
                if (!video.srcObject) {
                    video.srcObject = this.stream;
                }
                await this.waitForVideoMetadata(video);
                const playing = await this.playVideo(video);
                if (playing) {
                    await this.setupDetector();
                    this.cameraNeedsTap = false;
                    this.cameraStatus = this.activeCameraStatus();
                    this.scanLoop();
                    return;
                }
            }
            this.stopCamera();
            await this.startCamera(true);
        },
        async switchCamera() {
            if (this.switchingCamera) return;
            this.switchingCamera = true;
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            this.cameraStatus = 'Switching camera…';
            this.stopCamera();
            await this.startCamera();
            this.switchingCamera = false;
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
