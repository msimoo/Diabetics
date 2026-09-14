/**
 * Sari Clinic System - Smart Canvas Charts v2
 * Pure Canvas API - no external dependencies
 * Now with: scatter, dual-axis, stacked bar, heatmap, horizontal bar, range bands, tooltips
 */

class ClinicChart {
    constructor(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn('Canvas element not found:', canvasId);
            return;
        }
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.width = canvas.width;
        this.height = canvas.height;
        this.dpr = window.devicePixelRatio || 1;
        this.tooltipEl = null;
        this.tooltipData = null;

        if (this.dpr > 1) {
            canvas.width = this.width * this.dpr;
            canvas.height = this.height * this.dpr;
            canvas.style.width = this.width + 'px';
            canvas.style.height = this.height + 'px';
            this.ctx.scale(this.dpr, this.dpr);
        }

        this._createTooltip();
    }

    _createTooltip() {
        if (!this.tooltipEl) {
            this.tooltipEl = document.createElement('div');
            this.tooltipEl.style.cssText = `
                position: absolute; display: none; background: rgba(15,23,42,0.92);
                color: #fff; padding: 6px 12px; border-radius: 8px;
                font-size: 12px; font-family: Tajawal, sans-serif;
                pointer-events: none; z-index: 9999;
                white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            document.body.appendChild(this.tooltipEl);
        }
    }

    _showTooltip(text, x, y) {
        if (this.tooltipEl) {
            this.tooltipEl.innerHTML = text;
            this.tooltipEl.style.display = 'block';
            this.tooltipEl.style.left = Math.min(x + 12, window.innerWidth - 200) + 'px';
            this.tooltipEl.style.top = (y - 40) + 'px';
        }
    }

    _hideTooltip() {
        if (this.tooltipEl) this.tooltipEl.style.display = 'none';
    }

    _drawNoData(w, h) {
        const ctx = this.ctx;
        ctx.fillStyle = '#94a3b8';
        ctx.font = '14px Tajawal, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('لا توجد بيانات', w/2, h/2);
    }

    _getPadding(options) {
        return options.padding || { top: 20, right: 20, bottom: 40, left: 40 };
    }

    _drawGrid(ctx, w, h, padding, count = 4) {
        const chartW = w - padding.left - padding.right;
        const chartH = h - padding.top - padding.bottom;
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        ctx.setLineDash([5, 5]);
        for (let i = 0; i <= count; i++) {
            const y = padding.top + (chartH / count) * i;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(w - padding.right, y);
            ctx.stroke();
        }
        ctx.setLineDash([]);
        return { chartW, chartH };
    }

    _drawBackground(ctx, w, h, options) {
        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = options.bgColor || '#ffffff';
        ctx.fillRect(0, 0, w, h);
    }

    /**
     * Draw a line chart (with optional range/confidence band)
     */
    drawLineChart(labels, values, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = this._getPadding(options);
        this._drawBackground(ctx, w, h, options);

        if (!values || values.length === 0) { this._drawNoData(w, h); return; }

        const { chartW, chartH } = this._drawGrid(ctx, w, h, padding);
        const maxVal = Math.max(...values, 1);
        const minVal = Math.min(...values, 0);
        const range = maxVal - minVal || 1;
        const lineColor = options.lineColor || '#0a7e6e';
        const fillColor = options.fillColor || 'rgba(10, 126, 110, 0.08)';

        // Range/confidence band
        if (options.upperValues && options.lowerValues) {
            ctx.beginPath();
            options.upperValues.forEach((val, i) => {
                const x = padding.left + (chartW / (values.length - 1 || 1)) * i;
                const y = padding.top + chartH - ((val - minVal) / range) * chartH;
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            options.lowerValues.reverse().forEach((val, i) => {
                const idx = options.lowerValues.length - 1 - i;
                const x = padding.left + (chartW / (values.length - 1 || 1)) * idx;
                const y = padding.top + chartH - ((val - minVal) / range) * chartH;
                ctx.lineTo(x, y);
            });
            ctx.closePath();
            ctx.fillStyle = options.rangeColor || 'rgba(10, 126, 110, 0.12)';
            ctx.fill();
        }

        // Fill area under line
        ctx.beginPath();
        values.forEach((val, i) => {
            const x = padding.left + (chartW / (values.length - 1 || 1)) * i;
            const y = padding.top + chartH - ((val - minVal) / range) * chartH;
            if (i === 0) { ctx.moveTo(x, padding.top + chartH); ctx.lineTo(x, y); }
            else { ctx.lineTo(x, y); }
        });
        ctx.lineTo(padding.left + chartW, padding.top + chartH);
        ctx.closePath();
        ctx.fillStyle = fillColor;
        ctx.fill();

        // Line
        ctx.beginPath();
        values.forEach((val, i) => {
            const x = padding.left + (chartW / (values.length - 1 || 1)) * i;
            const y = padding.top + chartH - ((val - minVal) / range) * chartH;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.strokeStyle = lineColor;
        ctx.lineWidth = options.lineWidth || 3;
        ctx.lineJoin = 'round';
        ctx.stroke();

        // Points with tooltip data
        this._hitPoints = [];
        values.forEach((val, i) => {
            const x = padding.left + (chartW / (values.length - 1 || 1)) * i;
            const y = padding.top + chartH - ((val - minVal) / range) * chartH;

            ctx.beginPath();
            ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.strokeStyle = lineColor;
            ctx.lineWidth = 2;
            ctx.stroke();

            this._hitPoints.push({ x, y, r: 6, label: labels[i], value: val, tooltip: `${labels[i]}: ${val}` });
        });

        // X labels
        if (labels) {
            ctx.fillStyle = '#64748b';
            ctx.font = '11px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                const x = padding.left + (chartW / (labels.length - 1 || 1)) * i;
                ctx.fillText(label, x, h - 8);
            });
        }

        // Y labels
        ctx.textAlign = 'left';
        ctx.fillStyle = '#64748b';
        ctx.font = '10px Tajawal, sans-serif';
        [0, 0.25, 0.5, 0.75, 1].forEach(frac => {
            const val = minVal + range * frac;
            const y = padding.top + chartH - (frac * chartH);
            ctx.fillText(Number(val).toFixed(1), 2, y + 4);
        });

        this._attachHover();
    }

    /**
     * Draw a bar chart
     */
    drawBarChart(labels, values, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = this._getPadding(options);
        this._drawBackground(ctx, w, h, options);

        if (!values || values.length === 0) { this._drawNoData(w, h); return; }

        const { chartW, chartH } = this._drawGrid(ctx, w, h, padding);
        const maxVal = Math.max(...values, 1);
        const barCount = values.length;
        const barWidth = Math.min((chartW / barCount) * 0.6, 40);
        const gap = (chartW - barWidth * barCount) / (barCount + 1);
        const colors = options.colors || ['#0a7e6e', '#13a896', '#c9a84c', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899'];

        this._hitPoints = [];
        values.forEach((val, i) => {
            const x = padding.left + gap + (barWidth + gap) * i;
            const barH = (val / maxVal) * chartH;
            const y = padding.top + chartH - barH;

            ctx.fillStyle = colors[i % colors.length];
            ctx.beginPath();
            ctx.roundRect(x, y, barWidth, barH, [4, 4, 0, 0]);
            ctx.fill();

            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 11px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(val, x + barWidth/2, y - 6);

            this._hitPoints.push({ x: x + barWidth/2, y: y + barH/2, r: barH/2, label: labels[i], value: val, tooltip: `${labels[i]}: ${val}` });
        });

        if (labels) {
            ctx.fillStyle = '#64748b';
            ctx.font = '10px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                const x = padding.left + gap + (barWidth + gap) * i + barWidth/2;
                ctx.fillText(label, x, h - 8);
            });
        }

        this._attachHover();
    }

    /**
     * Draw a stacked bar chart
     */
    drawStackedBarChart(labels, datasets, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = this._getPadding(options);
        this._drawBackground(ctx, w, h, options);

        if (!labels || labels.length === 0) { this._drawNoData(w, h); return; }

        const chartW = w - padding.left - padding.right;
        const chartH = h - padding.top - padding.bottom;
        const barCount = labels.length;
        const barWidth = Math.min((chartW / barCount) * 0.7, 50);
        const gap = (chartW - barWidth * barCount) / (barCount + 1);
        const colors = options.colors || ['#0a7e6e', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#10b981'];

        const totals = labels.map((_, i) => datasets.reduce((sum, ds) => sum + (parseFloat(ds.values[i]) || 0), 0));
        const maxVal = Math.max(...totals, 1);

        this._hitPoints = [];
        labels.forEach((_, idx) => {
            let stackY = padding.top + chartH;
            datasets.forEach((ds, di) => {
                const val = parseFloat(ds.values[idx]) || 0;
                if (val === 0) return;
                const barH = (val / maxVal) * chartH;
                const x = padding.left + gap + (barWidth + gap) * idx;
                const y = stackY - barH;

                ctx.fillStyle = colors[di % colors.length];
                ctx.beginPath();
                ctx.roundRect(x, y, barWidth, barH, di === 0 ? [4, 4, 0, 0] : [0, 0, 0, 0]);
                ctx.fill();

                ctx.fillStyle = '#1e293b';
                ctx.font = '9px Tajawal, sans-serif';
                ctx.textAlign = 'center';
                const midY = y + barH/2;
                if (barH > 20) ctx.fillText(val, x + barWidth/2, midY + 4);

                this._hitPoints.push({ x: x + barWidth/2, y: midY, r: barH/2, label: ds.label, value: val, tooltip: `${ds.label}: ${val}` });
                stackY = y;
            });
        });

        if (labels) {
            ctx.fillStyle = '#64748b';
            ctx.font = '10px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                const x = padding.left + gap + (barWidth + gap) * i + barWidth/2;
                ctx.fillText(label, x, h - 8);
            });
        }

        const legendX = w - padding.right + 10;
        const legendY = padding.top;
        datasets.forEach((ds, i) => {
            const ly = legendY + i * 20;
            ctx.fillStyle = colors[i % colors.length];
            ctx.fillRect(legendX, ly, 12, 12);
            ctx.fillStyle = '#1e293b';
            ctx.font = '11px Tajawal, sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(ds.label, legendX + 18, ly + 10);
        });

        this._attachHover();
    }

    /**
     * Draw a pie/donut chart
     */
    drawPieChart(data, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const cx = w / 2;
        const cy = h / 2;
        const radius = Math.min(cx, cy) - 30;

        this._drawBackground(ctx, w, h, options);
        if (!data || data.length === 0) { this._drawNoData(w, h); return; }

        const colors = options.colors || ['#0a7e6e', '#c9a84c', '#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#ec4899'];
        const total = data.reduce((sum, item) => sum + item.value, 0);
        if (total === 0) { this._drawNoData(w, h); return; }

        let startAngle = -Math.PI / 2;
        this._hitPoints = [];

        data.forEach((item, i) => {
            const sliceAngle = (item.value / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();

            const midAngle = startAngle + sliceAngle / 2;
            const labelDist = radius + 15;
            const lx = cx + Math.cos(midAngle) * labelDist;
            const ly = cy + Math.sin(midAngle) * labelDist;
            ctx.fillStyle = '#1e293b';
            ctx.font = '11px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(`${item.label}: ${item.value}`, lx, ly + 4);

            const midX = cx + Math.cos(midAngle) * (radius * 0.6);
            const midY = cy + Math.sin(midAngle) * (radius * 0.6);
            const pct = Math.round((item.value / total) * 100);
            this._hitPoints.push({ x: midX, y: midY, r: 20, label: item.label, value: item.value, tooltip: `${item.label}: ${item.value} (${pct}%)` });

            startAngle += sliceAngle;
        });

        if (options.donut) {
            ctx.beginPath();
            ctx.arc(cx, cy, radius * 0.45, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 18px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(total, cx, cy + 6);
        }

        this._attachHover();
    }

    /**
     * Draw a scatter plot
     */
    drawScatterPlot(points, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = this._getPadding(options);
        this._drawBackground(ctx, w, h, options);

        if (!points || points.length === 0) { this._drawNoData(w, h); return; }

        const { chartW, chartH } = this._drawGrid(ctx, w, h, padding);
        const xValues = points.map(p => p.x);
        const yValues = points.map(p => p.y);
        const xMin = options.xMin !== undefined ? options.xMin : Math.min(...xValues, 0);
        const xMax = options.xMax !== undefined ? options.xMax : Math.max(...xValues, 1);
        const yMin = options.yMin !== undefined ? options.yMin : Math.min(...yValues, 0);
        const yMax = options.yMax !== undefined ? options.yMax : Math.max(...yValues, 1);
        const xRange = xMax - xMin || 1;
        const yRange = yMax - yMin || 1;
        const pointColor = options.pointColor || '#0a7e6e';
        const pointSize = options.pointSize || 5;

        this._hitPoints = [];
        points.forEach(p => {
            const px = padding.left + ((p.x - xMin) / xRange) * chartW;
            const py = padding.top + chartH - ((p.y - yMin) / yRange) * chartH;

            ctx.beginPath();
            ctx.arc(px, py, pointSize, 0, Math.PI * 2);
            ctx.fillStyle = options.getColor ? options.getColor(p) : pointColor;
            ctx.globalAlpha = 0.7;
            ctx.fill();
            ctx.globalAlpha = 1;
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 1.5;
            ctx.stroke();

            this._hitPoints.push({ x: px, y: py, r: pointSize + 3, label: p.label || '', value: `${p.x}, ${p.y}`, tooltip: p.tooltip || `${p.label || ''}: x=${p.x}, y=${p.y}` });
        });

        ctx.fillStyle = '#64748b';
        ctx.font = '10px Tajawal, sans-serif';
        ctx.textAlign = 'center';
        [0, 0.25, 0.5, 0.75, 1].forEach(frac => {
            const val = xMin + xRange * frac;
            const x = padding.left + frac * chartW;
            ctx.fillText(Number(val).toFixed(1), x, h - 8);
        });

        ctx.textAlign = 'right';
        [0, 0.25, 0.5, 0.75, 1].forEach(frac => {
            const val = yMin + yRange * frac;
            const y = padding.top + chartH - frac * chartH;
            ctx.fillText(Number(val).toFixed(1), padding.left - 5, y + 4);
        });

        if (options.xLabel) {
            ctx.fillStyle = '#64748b';
            ctx.font = '11px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(options.xLabel, w/2, h - 2);
        }
        if (options.yLabel) {
            ctx.save();
            ctx.translate(12, h/2);
            ctx.rotate(-Math.PI/2);
            ctx.fillStyle = '#64748b';
            ctx.font = '11px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(options.yLabel, 0, 0);
            ctx.restore();
        }

        this._attachHover();
    }

    /**
     * Draw a dual-axis line+bar chart
     */
    drawDualAxisChart(labels, leftValues, rightValues, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = options.padding || { top: 20, right: 50, bottom: 40, left: 40 };
        this._drawBackground(ctx, w, h, options);

        if (!leftValues || leftValues.length === 0) { this._drawNoData(w, h); return; }

        const { chartW, chartH } = this._drawGrid(ctx, w, h, padding);
        const leftMax = Math.max(...leftValues, 1);
        const rightMax = Math.max(...rightValues, 1);
        const leftColor = options.leftColor || '#0a7e6e';
        const rightColor = options.rightColor || '#3b82f6';

        // Left axis line
        ctx.beginPath();
        leftValues.forEach((val, i) => {
            const x = padding.left + (chartW / (leftValues.length - 1 || 1)) * i;
            const y = padding.top + chartH - (val / leftMax) * chartH;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = leftColor;
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.stroke();

        // Right axis bars
        if (rightValues && rightValues.length > 0) {
            const barWidth = Math.min((chartW / rightValues.length) * 0.4, 30);
            const gap = (chartW - barWidth * rightValues.length) / (rightValues.length + 1);
            rightValues.forEach((val, i) => {
                const x = padding.left + gap + (barWidth + gap) * i;
                const barH = (val / rightMax) * chartH;
                const y = padding.top + chartH - barH;

                ctx.fillStyle = rightColor;
                ctx.globalAlpha = 0.5;
                ctx.beginPath();
                ctx.roundRect(x, y, barWidth, barH, [3, 3, 0, 0]);
                ctx.fill();
                ctx.globalAlpha = 1;
            });
        }

        if (options.leftLabel) {
            ctx.fillStyle = leftColor;
            ctx.font = '10px Tajawal, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(options.leftLabel, padding.left - 5, padding.top - 5);
        }
        if (options.rightLabel) {
            ctx.fillStyle = rightColor;
            ctx.font = '10px Tajawal, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(options.rightLabel, w - 5, padding.top - 5);
        }

        if (labels) {
            ctx.fillStyle = '#64748b';
            ctx.font = '11px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            labels.forEach((label, i) => {
                const x = padding.left + (chartW / (labels.length - 1 || 1)) * i;
                ctx.fillText(label, x, h - 8);
            });
        }

        ctx.fillStyle = '#64748b';
        ctx.font = '10px Tajawal, sans-serif';
        ctx.textAlign = 'right';
        [0, 0.5, 1].forEach(frac => {
            const val = (leftMax * frac).toFixed(1);
            const y = padding.top + chartH - frac * chartH;
            ctx.fillText(val, padding.left - 5, y + 4);
        });

        ctx.textAlign = 'left';
        [0, 0.5, 1].forEach(frac => {
            const val = (rightMax * frac).toFixed(1);
            const y = padding.top + chartH - frac * chartH;
            ctx.fillText(val, w - padding.right + 5, y + 4);
        });

        this._hitPoints = [];
        leftValues.forEach((val, i) => {
            const x = padding.left + (chartW / (leftValues.length - 1 || 1)) * i;
            const y = padding.top + chartH - (val / leftMax) * chartH;
            this._hitPoints.push({ x, y, r: 6, label: labels[i], value: val, tooltip: `${options.leftLabel || 'يسار'}: ${val}` });
        });
        this._attachHover();
    }

    /**
     * Draw a horizontal bar chart
     */
    drawHorizontalBarChart(labels, values, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = options.padding || { top: 10, right: 60, bottom: 10, left: 100 };
        const chartW = w - padding.left - padding.right;
        const chartH = h - padding.top - padding.bottom;

        this._drawBackground(ctx, w, h, options);
        if (!values || values.length === 0) { this._drawNoData(w, h); return; }

        const maxVal = Math.max(...values, 1);
        const barH = Math.min(chartH / values.length * 0.7, 30);
        const gap = (chartH - barH * values.length) / (values.length + 1);
        const colors = options.colors || ['#0a7e6e', '#3b82f6', '#f59e0b', '#8b5cf6', '#10b981', '#ec4899'];

        this._hitPoints = [];
        values.forEach((val, i) => {
            const y = padding.top + gap + (barH + gap) * i;
            const barW = (val / maxVal) * chartW;

            ctx.fillStyle = colors[i % colors.length];
            ctx.beginPath();
            ctx.roundRect(padding.left, y, barW, barH, [0, 4, 4, 0]);
            ctx.fill();

            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 12px Tajawal, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(val, padding.left + barW + 8, y + barH/2 + 4);

            ctx.textAlign = 'left';
            ctx.fillStyle = '#1e293b';
            ctx.font = '12px Tajawal, sans-serif';
            ctx.fillText(labels[i], padding.left - 8, y + barH/2 + 4);

            this._hitPoints.push({ x: padding.left + barW/2, y: y + barH/2, r: barH/2, label: labels[i], value: val, tooltip: `${labels[i]}: ${val}` });
        });

        this._attachHover();
    }

    /**
     * Draw a heatmap grid
     */
    drawHeatmap(data, options = {}) {
        const ctx = this.ctx;
        const w = this.width;
        const h = this.height;
        const padding = options.padding || { top: 40, right: 20, bottom: 60, left: 80 };
        const chartW = w - padding.left - padding.right;
        const chartH = h - padding.top - padding.bottom;

        this._drawBackground(ctx, w, h, options);
        if (!data || data.length === 0) { this._drawNoData(w, h); return; }

        const rows = data.length;
        const cols = data[0].length;
        const cellW = chartW / cols;
        const cellH = chartH / rows;
        const colorScale = options.colorScale || ['#fee2e2', '#fecaca', '#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c', '#991b1b'];

        let maxVal = 0;
        data.forEach(row => row.forEach(c => { if (c.value > maxVal) maxVal = c.value; }));
        maxVal = maxVal || 1;

        this._hitPoints = [];
        data.forEach((row, ri) => {
            row.forEach((cell, ci) => {
                const x = padding.left + ci * cellW;
                const y = padding.top + ri * cellH;
                const ratio = cell.value / maxVal;
                const colorIdx = Math.min(Math.floor(ratio * colorScale.length), colorScale.length - 1);

                ctx.fillStyle = colorScale[colorIdx];
                ctx.fillRect(x, y, cellW, cellH);
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1;
                ctx.strokeRect(x, y, cellW, cellH);

                ctx.fillStyle = ratio > 0.5 ? '#fff' : '#1e293b';
                ctx.font = 'bold 12px Tajawal, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(cell.value, x + cellW/2, y + cellH/2 + 4);

                this._hitPoints.push({
                    x: x + cellW/2, y: y + cellH/2, r: Math.min(cellW, cellH) / 2,
                    label: cell.label || '', value: cell.value,
                    tooltip: `${cell.xLabel || ''} × ${cell.yLabel || ''}: ${cell.value}`
                });
            });
        });

        ctx.fillStyle = '#1e293b';
        ctx.font = '11px Tajawal, sans-serif';
        ctx.textAlign = 'right';
        data.forEach((row, ri) => {
            const y = padding.top + ri * cellH + cellH/2 + 4;
            ctx.fillText(row[0].yLabel || '', padding.left - 8, y);
        });

        ctx.textAlign = 'center';
        if (data[0]) data[0].forEach((cell, ci) => {
            const x = padding.left + ci * cellW + cellW/2;
            ctx.fillText(cell.xLabel || '', x, padding.top - 10);
        });

        this._attachHover();
    }

    _attachHover() {
        const canvas = this.canvas;
        const self = this;

        canvas.onmousemove = function(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = self.width / rect.width;
            const scaleY = self.height / rect.height;
            const mx = (e.clientX - rect.left) * scaleX;
            const my = (e.clientY - rect.top) * scaleY;

            let found = false;
            if (self._hitPoints) {
                for (const p of self._hitPoints) {
                    const dist = Math.sqrt((mx - p.x) ** 2 + (my - p.y) ** 2);
                    if (dist <= p.r) {
                        self._showTooltip(p.tooltip, e.clientX, e.clientY);
                        found = true;
                        canvas.style.cursor = 'pointer';
                        break;
                    }
                }
            }
            if (!found) {
                self._hideTooltip();
                canvas.style.cursor = 'default';
            }
        };

        canvas.onmouseleave = function() {
            self._hideTooltip();
        };
    }
}

// Polyfill roundRect
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, radii) {
        const r = Array.isArray(radii) ? radii : [radii, radii, radii, radii];
        this.moveTo(x + r[0], y);
        this.lineTo(x + w - r[1], y);
        this.quadraticCurveTo(x + w, y, x + w, y + r[1]);
        this.lineTo(x + w, y + h - r[2]);
        this.quadraticCurveTo(x + w, y + h, x + w - r[2], y + h);
        this.lineTo(x + r[3], y + h);
        this.quadraticCurveTo(x, y + h, x, y + h - r[3]);
        this.lineTo(x, y + r[0]);
        this.quadraticCurveTo(x, y, x + r[0], y);
        this.closePath();
    };
}
