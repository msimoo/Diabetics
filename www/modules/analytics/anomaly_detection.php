<?php
/**
 * Anomaly Detection — Smart alerts for sudden changes, slow healing, non-response
 */
$page_title = '🔍 كشف الشذوذ | Anomaly Detection';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
    .anomaly-card { border-radius: 12px; padding: 1rem; border: 1px solid var(--border); transition: var(--transition); margin-bottom: 0.75rem; }
    .anomaly-card:hover { box-shadow: var(--shadow-lg); transform: translateX(-2px); }
    .severity-critical { border-right: 4px solid #7f1d1d; background: linear-gradient(135deg, #fef2f2, #fff); }
    .severity-high { border-right: 4px solid #dc2626; background: linear-gradient(135deg, #fef2f2, #fff); }
    .severity-medium { border-right: 4px solid #f59e0b; background: linear-gradient(135deg, #fffbeb, #fff); }
    .severity-low { border-right: 4px solid #3b82f6; background: linear-gradient(135deg, #eff6ff, #fff); }
    .anomaly-loading { text-align: center; padding: 4rem; }
    .anomaly-loading .spinner { margin: 0 auto; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🔍 كشف الشذوذ السريري</h1>
    <p class="page-subtitle">Anomaly Detection — تنبيهات ذكية للتغيرات المفاجئة وعدم الاستجابة</p>
</div>

<!-- Summary -->
<div class="stats-grid mb-4" id="anomalySummary">
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#7f1d1d,#dc2626);"></div><div class="stat-value" style="color:#7f1d1d;" id="criticalCount">0</div><div class="stat-label">🔴 حرجة</div></div>
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#dc2626,#ef4444);"></div><div class="stat-value" style="color:#dc2626;" id="highCount">0</div><div class="stat-label">🟠 عالية</div></div>
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div><div class="stat-value" style="color:#d97706;" id="medCount">0</div><div class="stat-label">🟡 متوسطة</div></div>
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div><div class="stat-value" style="color:#1d4ed8;" id="anomalyTotal">0</div><div class="stat-label">📊 الإجمالي</div></div>
</div>

<!-- Filter Tabs -->
<div class="flex gap-2 mb-4 flex-wrap">
    <button class="btn btn-sm btn-primary" onclick="filterAnomalies('all')">الكل</button>
    <button class="btn btn-sm btn-secondary" onclick="filterAnomalies('critical')">🔴 حرجة</button>
    <button class="btn btn-sm btn-secondary" onclick="filterAnomalies('high')">🟠 عالية</button>
    <button class="btn btn-sm btn-secondary" onclick="filterAnomalies('medium')">🟡 متوسطة</button>
    <button class="btn btn-sm btn-secondary" onclick="filterAnomalies('low')">🔵 منخفضة</button>
</div>

<!-- Anomalies List -->
<div id="anomaliesList">
    <div class="anomaly-loading"><div class="spinner"></div><p class="mt-2 text-muted">جاري تحليل البيانات...</p></div>
</div>

</div></div></div>

<script>
const severityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
const severityLabels = { critical: '🔴 حرجة', high: '🟠 عالية', medium: '🟡 متوسطة', low: '🔵 منخفضة' };

function filterAnomalies(level) {
    document.querySelectorAll('.anomaly-card').forEach(card => {
        if (level === 'all' || card.dataset.severity === level) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    fetch(BASE_URL + '/api/analytics.php?action=anomalies')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('anomaliesList');
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="card" style="text-align:center;padding:3rem;"><div style="font-size:3rem;">✅</div><h3>لا توجد حالات شذوذ</h3><p class="text-muted">جميع المرضى ضمن النطاق الطبيعي</p></div>';
                return;
            }

            // Update counts
            const critical = data.filter(a => a.severity === 'critical').length;
            const high = data.filter(a => a.severity === 'high').length;
            const medium = data.filter(a => a.severity === 'medium').length;
            document.getElementById('criticalCount').textContent = critical;
            document.getElementById('highCount').textContent = high;
            document.getElementById('medCount').textContent = medium;
            document.getElementById('anomalyTotal').textContent = data.length;

            // Sort by severity
            data.sort((a, b) => (severityOrder[a.severity] || 99) - (severityOrder[b.severity] || 99));

            container.innerHTML = data.map(a => `
                <div class="anomaly-card severity-${a.severity}" data-severity="${a.severity}">
                    <div class="flex justify-between items-start flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span style="font-size:1.5rem;">${a.icon}</span>
                            <div>
                                <div style="font-weight:700;font-size:1rem;">${a.title}</div>
                                <div style="font-size:0.85rem;color:var(--text-muted);">
                                    <a href="${BASE_URL}/modules/patients/view.php?id=${a.patient_id}" style="color:var(--teal);font-weight:600;">${a.patient_name}</a>
                                    — 📁 ${a.file_number}
                                </div>
                            </div>
                        </div>
                        <span class="badge badge-${a.severity === 'critical' ? 'danger' : a.severity === 'high' ? 'warning' : 'info'}" 
                              style="font-size:11px;padding:4px 10px;">
                            ${severityLabels[a.severity] || a.severity}
                        </span>
                    </div>
                    <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border);font-size:14px;">
                        ${a.detail}
                    </div>
                    <div class="mt-2 flex gap-2">
                        <a href="${BASE_URL}/modules/patients/view.php?id=${a.patient_id}" class="btn btn-sm btn-secondary">👤 عرض المريض</a>
                        <a href="${BASE_URL}/modules/analytics/patient_timeline.php?id=${a.patient_id}" class="btn btn-sm btn-secondary">📋 الجدول الزمني</a>
                    </div>
                </div>
            `).join('');
        })
        .catch(() => {
            document.getElementById('anomaliesList').innerHTML = '<div class="card" style="text-align:center;padding:3rem;color:var(--red);"><h3>❌ خطأ في تحميل البيانات</h3></div>';
        });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
