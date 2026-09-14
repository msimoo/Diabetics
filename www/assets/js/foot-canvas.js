/**
 * Sari Clinic System - Interactive Foot Canvas v2
 * Upgraded with realistic foot anatomy, gradient skin tones, heatmap wound markers,
 * zone coloring, better UX, and responsive design
 */

class FootCanvas {
    constructor(canvasId, footType) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.footType = footType; // 'right' or 'left'
        this.markers = [];
        this.dpr = window.devicePixelRatio || 1;
        this.animFrame = null;
        this.hoveredMarker = -1;
        this.setupCanvas();
        this.init();
    }

    setupCanvas() {
        const rect = this.canvas.getBoundingClientRect();
        this.displayWidth = rect.width || 160;
        this.displayHeight = rect.height || 200;
        this.canvas.width = this.displayWidth * this.dpr;
        this.canvas.height = this.displayHeight * this.dpr;
        this.ctx.scale(this.dpr, this.dpr);
        this.width = this.displayWidth;
        this.height = this.displayHeight;
    }

    init() {
        this.drawFoot();
        this.canvas.addEventListener('click', (e) => this.handleClick(e));
        this.canvas.addEventListener('mousemove', (e) => this.handleHover(e));
        this.canvas.addEventListener('mouseleave', () => this.handleLeave());
        this.canvas.addEventListener('dblclick', () => this.clearMarkers());
        
        // Load existing markers
        const xInput = document.getElementById(`wound_x_${this.footType}`);
        const yInput = document.getElementById(`wound_y_${this.footType}`);
        if (xInput && yInput && xInput.value && yInput.value) {
            this.markers.push({ x: parseFloat(xInput.value), y: parseFloat(yInput.value), type: 'ulcer' });
            this.drawFoot();
            this.drawMarkers();
        }
    }

    drawFoot() {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;

        ctx.clearRect(0, 0, w, h);

        // Gradient skin background
        const gradient = ctx.createLinearGradient(0, 0, w, h);
        gradient.addColorStop(0, '#fde68a');
        gradient.addColorStop(0.3, '#fef3c7');
        gradient.addColorStop(0.6, '#fde68a');
        gradient.addColorStop(1, '#fcd34d');
        ctx.fillStyle = gradient;
        
        ctx.beginPath();
        if (this.footType === 'right') {
            // Right foot - more natural curve
            ctx.moveTo(w * 0.38, h * 0.04);
            ctx.bezierCurveTo(w * 0.42, h * 0.01, w * 0.48, h * 0.01, w * 0.55, h * 0.02);
            ctx.bezierCurveTo(w * 0.7, h * 0.04, w * 0.82, h * 0.12, w * 0.85, h * 0.25);
            ctx.bezierCurveTo(w * 0.88, h * 0.38, w * 0.84, h * 0.5, w * 0.83, h * 0.65);
            ctx.bezierCurveTo(w * 0.82, h * 0.78, w * 0.84, h * 0.85, w * 0.82, h * 0.92);
            ctx.bezierCurveTo(w * 0.8, h * 0.98, w * 0.7, h * 0.99, w * 0.6, h * 0.99);
            ctx.bezierCurveTo(w * 0.5, h * 0.99, w * 0.38, h * 0.98, w * 0.32, h * 0.92);
            ctx.bezierCurveTo(w * 0.26, h * 0.85, w * 0.27, h * 0.75, w * 0.28, h * 0.65);
            ctx.bezierCurveTo(w * 0.26, h * 0.5, w * 0.25, h * 0.38, w * 0.27, h * 0.25);
            ctx.bezierCurveTo(w * 0.29, h * 0.12, w * 0.34, h * 0.04, w * 0.38, h * 0.04);
        } else {
            // Left foot (mirrored)
            ctx.moveTo(w * 0.62, h * 0.04);
            ctx.bezierCurveTo(w * 0.58, h * 0.01, w * 0.52, h * 0.01, w * 0.45, h * 0.02);
            ctx.bezierCurveTo(w * 0.3, h * 0.04, w * 0.18, h * 0.12, w * 0.15, h * 0.25);
            ctx.bezierCurveTo(w * 0.12, h * 0.38, w * 0.16, h * 0.5, w * 0.17, h * 0.65);
            ctx.bezierCurveTo(w * 0.18, h * 0.78, w * 0.16, h * 0.85, w * 0.18, h * 0.92);
            ctx.bezierCurveTo(w * 0.2, h * 0.98, w * 0.3, h * 0.99, w * 0.4, h * 0.99);
            ctx.bezierCurveTo(w * 0.5, h * 0.99, w * 0.62, h * 0.98, w * 0.68, h * 0.92);
            ctx.bezierCurveTo(w * 0.74, h * 0.85, w * 0.73, h * 0.75, w * 0.72, h * 0.65);
            ctx.bezierCurveTo(w * 0.74, h * 0.5, w * 0.75, h * 0.38, w * 0.73, h * 0.25);
            ctx.bezierCurveTo(w * 0.71, h * 0.12, w * 0.66, h * 0.04, w * 0.62, h * 0.04);
        }
        ctx.closePath();
        ctx.fill();
        
        // Foot outline
        ctx.strokeStyle = '#d97706';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Toes - realistic 5 toes
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#b45309';
        if (this.footType === 'right') {
            const toePositions = [
                { x: 0.29, y: 0.04, r: 0.035 },  // big toe
                { x: 0.36, y: 0.02, r: 0.028 },
                { x: 0.44, y: 0.015, r: 0.025 },
                { x: 0.52, y: 0.02, r: 0.022 },
                { x: 0.59, y: 0.03, r: 0.02 }    // pinky
            ];
            toePositions.forEach((t, i) => {
                const gradient = ctx.createRadialGradient(w * t.x, h * t.y, 1, w * t.x, h * t.y, w * t.r);
                gradient.addColorStop(0, '#fef3c7');
                gradient.addColorStop(0.6, '#fde68a');
                gradient.addColorStop(1, '#fcd34d');
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.ellipse(w * t.x, h * t.y, w * t.r, h * (t.r * 0.7), -0.2, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();
                
                // Nail
                ctx.fillStyle = '#fbbf24';
                ctx.beginPath();
                ctx.ellipse(w * t.x, h * (t.y - 0.005), w * t.r * 0.4, h * t.r * 0.3, 0, 0, Math.PI * 2);
                ctx.fill();
            });
        } else {
            const toePositions = [
                { x: 0.71, y: 0.04, r: 0.035 },
                { x: 0.64, y: 0.02, r: 0.028 },
                { x: 0.56, y: 0.015, r: 0.025 },
                { x: 0.48, y: 0.02, r: 0.022 },
                { x: 0.41, y: 0.03, r: 0.02 }
            ];
            toePositions.forEach((t, i) => {
                const gradient = ctx.createRadialGradient(w * t.x, h * t.y, 1, w * t.x, h * t.y, w * t.r);
                gradient.addColorStop(0, '#fef3c7');
                gradient.addColorStop(0.6, '#fde68a');
                gradient.addColorStop(1, '#fcd34d');
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.ellipse(w * t.x, h * t.y, w * t.r, h * (t.r * 0.7), 0.2, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();
                
                // Nail
                ctx.fillStyle = '#fbbf24';
                ctx.beginPath();
                ctx.ellipse(w * t.x, h * (t.y - 0.005), w * t.r * 0.4, h * t.r * 0.3, 0, 0, Math.PI * 2);
                ctx.fill();
            });
        }

        // Colored anatomical zones with labels
        const zones = [
            { yStart: 0.08, yEnd: 0.32, color: 'rgba(239, 68, 68, 0.08)', label: 'الأصابع', labelY: 0.22 },
            { yStart: 0.32, yEnd: 0.52, color: 'rgba(245, 158, 11, 0.06)', label: 'مشط القدم', labelY: 0.42 },
            { yStart: 0.52, yEnd: 0.72, color: 'rgba(59, 130, 246, 0.06)', label: 'قوس القدم', labelY: 0.62 },
            { yStart: 0.72, yEnd: 0.95, color: 'rgba(16, 185, 129, 0.06)', label: 'الكعب', labelY: 0.84 }
        ];

        zones.forEach(z => {
            ctx.fillStyle = z.color;
            ctx.fillRect(w * 0.15, h * z.yStart, w * 0.7, h * (z.yEnd - z.yStart));
        });

        // Zone divider lines (dashed)
        ctx.setLineDash([3, 4]);
        ctx.strokeStyle = 'rgba(10, 126, 110, 0.25)';
        ctx.lineWidth = 1;
        [0.32, 0.52, 0.72].forEach(yFrac => {
            ctx.beginPath();
            ctx.moveTo(w * 0.15, h * yFrac);
            ctx.lineTo(w * 0.85, h * yFrac);
            ctx.stroke();
        });
        ctx.setLineDash([]);

        // Zone labels with icons
        const labels = [
            { text: '🦶 الأصابع', y: 0.22, color: 'rgba(239,68,68,0.6)' },
            { text: '👣 المشط', y: 0.42, color: 'rgba(245,158,11,0.6)' },
            { text: '〽️ القوس', y: 0.62, color: 'rgba(59,130,246,0.6)' },
            { text: '🔴 الكعب', y: 0.84, color: 'rgba(16,185,129,0.6)' }
        ];
        
        labels.forEach(l => {
            ctx.fillStyle = l.color;
            ctx.font = '9px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(l.text, w * 0.5, h * l.y);
        });

        // Heel pressure point highlight
        ctx.fillStyle = 'rgba(239, 68, 68, 0.06)';
        ctx.beginPath();
        ctx.arc(w * 0.5, h * 0.88, w * 0.12, 0, Math.PI * 2);
        ctx.fill();
    }

    handleClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.width / rect.width;
        const scaleY = this.height / rect.height;
        const x = (((e.clientX - rect.left) * scaleX) / this.width * 100).toFixed(1);
        const y = (((e.clientY - rect.top) * scaleY) / this.height * 100).toFixed(1);

        // Animate click
        this.animateClick(x, y);

        // Replace previous marker (one wound per foot)
        this.markers = [{ x: parseFloat(x), y: parseFloat(y), type: 'ulcer' }];
        this.drawFoot();
        this.drawMarkers();

        // Save coordinates
        document.getElementById(`wound_x_${this.footType}`).value = x;
        document.getElementById(`wound_y_${this.footType}`).value = y;
        document.getElementById(`wound_foot`).value = this.footType === 'right' ? 'يمنى' : 'يسرى';
    }

    animateClick(x, y) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const cx = (x / 100) * w;
        const cy = (y / 100) * h;
        let radius = 2;
        let opacity = 0.6;

        const anim = () => {
            ctx.save();
            ctx.beginPath();
            ctx.arc(cx, cy, radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(239, 68, 68, ${opacity})`;
            ctx.fill();
            ctx.restore();
            radius += 1.5;
            opacity -= 0.03;
            if (opacity > 0 && radius < 25) {
                this.animFrame = requestAnimationFrame(anim);
            }
        };
        if (this.animFrame) cancelAnimationFrame(this.animFrame);
        anim();
    }

    handleHover(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.width / rect.width;
        const scaleY = this.height / rect.height;
        const mx = (e.clientX - rect.left) * scaleX;
        const my = (e.clientY - rect.top) * scaleY;

        let found = false;
        for (const m of this.markers) {
            const cx = (m.x / 100) * this.width;
            const cy = (m.y / 100) * this.height;
            const dist = Math.sqrt((mx - cx) ** 2 + (my - cy) ** 2);
            if (dist < 12) {
                found = true;
                this.canvas.style.cursor = 'pointer';
                this.showTooltip(`${m.type === 'ulcer' ? '🩹' : '📍'} جرح: (${m.x}%, ${m.y}%)`, e.clientX, e.clientY);
                break;
            }
        }
        if (!found) {
            this.canvas.style.cursor = 'crosshair';
            this.hideTooltip();
        }
    }

    handleLeave() {
        this.canvas.style.cursor = 'default';
        this.hideTooltip();
    }

    showTooltip(text, x, y) {
        let tooltip = document.querySelector('.foot-canvas-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'foot-canvas-tooltip';
            tooltip.style.cssText = `
                position: fixed; background: rgba(15,23,42,0.92); color: #fff;
                padding: 4px 10px; border-radius: 6px; font-size: 11px;
                font-family: Tajawal, sans-serif; pointer-events: none;
                z-index: 9999; white-space: nowrap;
            `;
            document.body.appendChild(tooltip);
        }
        tooltip.innerHTML = text;
        tooltip.style.display = 'block';
        tooltip.style.left = Math.min(x + 10, window.innerWidth - 150) + 'px';
        tooltip.style.top = (y - 30) + 'px';
    }

    hideTooltip() {
        const tooltip = document.querySelector('.foot-canvas-tooltip');
        if (tooltip) tooltip.style.display = 'none';
    }

    drawMarkers() {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;

        this.markers.forEach(m => {
            const cx = (m.x / 100) * w;
            const cy = (m.y / 100) * h;

            // Outer glow - heatmap effect
            const gradient = ctx.createRadialGradient(cx, cy, 1, cx, cy, 18);
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.5)');
            gradient.addColorStop(0.5, 'rgba(239, 68, 68, 0.2)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(cx, cy, 18, 0, Math.PI * 2);
            ctx.fill();

            // Pulse ring animation
            const pulse = Date.now() / 500;
            const pulseRadius = 8 + Math.sin(pulse) * 2;
            ctx.beginPath();
            ctx.arc(cx, cy, pulseRadius, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(239, 68, 68, 0.3)';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Main wound marker - red circle with white border
            ctx.beginPath();
            ctx.arc(cx, cy, 7, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(220, 38, 38, 0.9)';
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2.5;
            ctx.stroke();

            // Cross mark
            ctx.beginPath();
            ctx.moveTo(cx - 3, cy - 3);
            ctx.lineTo(cx + 3, cy + 3);
            ctx.moveTo(cx + 3, cy - 3);
            ctx.lineTo(cx - 3, cy + 3);
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Size label
            ctx.fillStyle = 'rgba(15, 23, 42, 0.8)';
            ctx.font = 'bold 8px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('🩹', cx, cy - 14);
        });

        // Pulse animation handled statically via Math.sin(Date.now()/500) above - no infinite loop needed
    }

    clearMarkers() {
        this.markers = [];
        this.drawFoot();
        document.getElementById(`wound_x_${this.footType}`).value = '';
        document.getElementById(`wound_y_${this.footType}`).value = '';
        this.hideTooltip();
    }
}
