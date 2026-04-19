import { Controller } from '@hotwired/stimulus';
import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';
import { getComponent } from '@symfony/ux-live-component';

/** Formats found on food packaging — everything else is skipped to save CPU. */
const PANTRY_FORMATS = [
    BarcodeFormat.EAN_13,
    BarcodeFormat.EAN_8,
    BarcodeFormat.UPC_A,
    BarcodeFormat.UPC_E,
];

/** MediaTrackConstraints: prefer the rear camera and a high-res stream. */
const VIDEO_CONSTRAINTS = {
    facingMode: { ideal: 'environment' },
    width: { ideal: 1280 },
    height: { ideal: 720 },
};

/** How long the green overlay stays drawn after the last detection. */
const OVERLAY_LINGER_MS = 1500;

/*
 * Continuously pre-detects barcodes so the user sees which one would be sent,
 * but only dispatches to the Live Component when the user clicks the button.
 * This gives the reliability of continuous scanning with the control of a
 * manual trigger — no accidental double-adds.
 *
 * Targets:
 *   - video      : <video> preview element.
 *   - overlay    : <canvas> that highlights the detected barcode in real time.
 *   - captureBtn : primary "Confirm scan" button — armed when a code is visible.
 *   - status     : inline element with live feedback.
 *   - manual     : <input> for manual barcode entry.
 */
export default class extends Controller {
    static targets = ['video', 'overlay', 'captureBtn', 'status', 'manual'];

    async connect() {
        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, PANTRY_FORMATS);
        hints.set(DecodeHintType.TRY_HARDER, true);

        // 3rd arg: timeBetweenScansMillis (default 500). Lower = more attempts/s.
        this._reader = new BrowserMultiFormatReader(hints, 150);

        this._stopControls = null;
        this._stream = null;
        this._detected = null; // { barcode, ts, result } — latched until click.
        this._overlayTimer = null;

        if (!this.hasVideoTarget) return;

        this._onVideoResize = () => this._syncOverlaySize();
        this.videoTarget.addEventListener('loadedmetadata', this._onVideoResize);
        this.videoTarget.addEventListener('playing', this._onVideoResize);
        window.addEventListener('resize', this._onVideoResize);

        this._armButton(false);
        this._setStatus('Activation de la caméra…', null, 0);

        try {
            this._stopControls = await this._reader.decodeFromConstraints(
                { video: VIDEO_CONSTRAINTS, audio: false },
                this.videoTarget,
                (result, _err, _controls) => {
                    if (!result) return;
                    this._onDetected(result);
                },
            );
            this._stream = this.videoTarget.srcObject;
            this.element.dataset.scanning = 'true';
            this._setStatus('Cadrez un code-barres…', null, 0);
        } catch (e) {
            console.warn('Caméra indisponible:', e?.message ?? e);
            this._setStatus('Caméra indisponible — utilisez la saisie manuelle.', 'error', 0);
            this._armButton(false, 'Caméra indisponible');
        }
    }

    disconnect() {
        if (this._stopControls && typeof this._stopControls.stop === 'function') {
            try { this._stopControls.stop(); } catch (_) { /* noop */ }
        }
        if (this._stream) {
            for (const track of this._stream.getTracks()) {
                try { track.stop(); } catch (_) { /* noop */ }
            }
        }
        if (this._overlayTimer) { clearTimeout(this._overlayTimer); this._overlayTimer = null; }
        if (this.hasVideoTarget && this._onVideoResize) {
            this.videoTarget.removeEventListener('loadedmetadata', this._onVideoResize);
            this.videoTarget.removeEventListener('playing', this._onVideoResize);
            window.removeEventListener('resize', this._onVideoResize);
            this.videoTarget.srcObject = null;
        }
        delete this.element.dataset.scanning;
        this._stopControls = null;
        this._stream = null;
        this._reader = null;
    }

    /**
     * Called by ZXing on every successful decode. Does NOT hit the backend.
     * The button is "latched": once a code is detected, it stays armed until
     * the user clicks — even if ZXing momentarily loses the code between
     * frames — so the button never flickers.
     */
    _onDetected(result) {
        const barcode = String(result.getText());
        const isNew = !this._detected || this._detected.barcode !== barcode;

        this._detected = { barcode, ts: Date.now(), result };
        this._drawHit(result);

        if (isNew) {
            this._setStatus(`Code détecté : ${barcode}`, 'ok', 0);
            this._armButton(true, '✅ Ajouter ce produit');
        }

        // Schedule an overlay fade-out (purely cosmetic — button stays armed).
        if (this._overlayTimer) clearTimeout(this._overlayTimer);
        this._overlayTimer = setTimeout(() => {
            this._clearOverlay();
            this._overlayTimer = null;
        }, OVERLAY_LINGER_MS);
    }

    _resetDetection() {
        this._detected = null;
        if (this._overlayTimer) { clearTimeout(this._overlayTimer); this._overlayTimer = null; }
        this._clearOverlay();
        this._armButton(false);
        this._setStatus('Cadrez un code-barres…', null, 0);
    }

    /** User confirms — send the currently detected barcode to the backend. */
    capture(event) {
        event?.preventDefault();
        if (!this._detected) {
            this._setStatus('Aucun code détecté pour l\'instant.', 'error');

            return;
        }

        const barcode = this._detected.barcode;
        this._setStatus(`Envoyé : ${barcode}`, 'ok');
        this._dispatchScan(barcode);

        // Reset: require a fresh detection before allowing another click.
        this._resetDetection();
    }

    submitManual(event) {
        event.preventDefault();
        if (!this.hasManualTarget) return;
        const value = this.manualTarget.value.trim();
        if (value === '') return;
        this._dispatchScan(value);
        this.manualTarget.value = '';
    }

    async _dispatchScan(barcode) {
        try {
            const component = await getComponent(this.element);
            component.action('scan', { barcode });
        } catch (e) {
            console.error('LiveComponent introuvable pour le scanner:', e);
        }
    }

    _armButton(armed, label) {
        if (!this.hasCaptureBtnTarget) return;
        const btn = this.captureBtnTarget;
        btn.disabled = !armed;
        btn.classList.toggle('btn-armed', armed);
        if (label) btn.textContent = label;
        else if (!armed) btn.textContent = '📷 En attente d\'un code…';
    }

    _setStatus(message, state, autoClearMs = 4000) {
        if (!this.hasStatusTarget) return;
        this.statusTarget.textContent = message ?? '';
        if (state) this.statusTarget.dataset.state = state;
        else delete this.statusTarget.dataset.state;
        if (this._statusTimer) { clearTimeout(this._statusTimer); this._statusTimer = null; }
        if (autoClearMs > 0 && message) {
            this._statusTimer = setTimeout(() => {
                if (!this.hasStatusTarget) return;
                this.statusTarget.textContent = '';
                delete this.statusTarget.dataset.state;
                this._statusTimer = null;
            }, autoClearMs);
        }
    }

    _clearOverlay() {
        if (!this.hasOverlayTarget) return;
        const ctx = this.overlayTarget.getContext('2d');
        if (ctx) ctx.clearRect(0, 0, this.overlayTarget.width, this.overlayTarget.height);
    }

    _syncOverlaySize() {
        if (!this.hasOverlayTarget || !this.hasVideoTarget) return;
        const video = this.videoTarget;
        const canvas = this.overlayTarget;
        const w = video.clientWidth;
        const h = video.clientHeight;
        const dpr = window.devicePixelRatio || 1;
        if (w === 0 || h === 0) return;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        const ctx = canvas.getContext('2d');
        if (ctx) ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    _drawHit(result) {
        if (!this.hasOverlayTarget || !this.hasVideoTarget) return;
        const canvas = this.overlayTarget;
        const video = this.videoTarget;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        this._syncOverlaySize();
        const dispW = video.clientWidth;
        const dispH = video.clientHeight;
        const srcW = video.videoWidth || dispW;
        const srcH = video.videoHeight || dispH;

        // `object-fit: cover` — compute the same transform so overlay points align with what the user sees.
        const scale = Math.max(dispW / srcW, dispH / srcH);
        const renderedW = srcW * scale;
        const renderedH = srcH * scale;
        const offsetX = (dispW - renderedW) / 2;
        const offsetY = (dispH - renderedH) / 2;

        const points = typeof result.getResultPoints === 'function' ? result.getResultPoints() : [];
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!points || points.length === 0) return;

        const xs = points.map(p => p.getX() * scale + offsetX);
        const ys = points.map(p => p.getY() * scale + offsetY);
        const minX = Math.min(...xs), maxX = Math.max(...xs);
        const minY = Math.min(...ys), maxY = Math.max(...ys);
        const pad = 12;

        ctx.save();
        ctx.strokeStyle = '#1abc4c';
        ctx.lineWidth = 3;
        ctx.shadowColor = 'rgba(0,0,0,.6)';
        ctx.shadowBlur = 4;
        this._roundedRect(ctx, minX - pad, minY - pad, (maxX - minX) + 2 * pad, (maxY - minY) + 2 * pad, 6);
        ctx.stroke();

        ctx.fillStyle = '#1abc4c';
        ctx.shadowBlur = 0;
        for (let i = 0; i < points.length; i++) {
            ctx.beginPath();
            ctx.arc(xs[i], ys[i], 4, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.restore();
    }

    _roundedRect(ctx, x, y, w, h, r) {
        const radius = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.arcTo(x + w, y, x + w, y + h, radius);
        ctx.arcTo(x + w, y + h, x, y + h, radius);
        ctx.arcTo(x, y + h, x, y, radius);
        ctx.arcTo(x, y, x + w, y, radius);
        ctx.closePath();
    }
}
