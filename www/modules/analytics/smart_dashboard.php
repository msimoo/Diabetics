<?php
/**
 * Smart Dashboard — Role-based analytics homepage with insights, anomalies, and KPIs
 */
$page_title = '🧠 لوحة التحكم الذكية | Smart Dashboard';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$user_role = $_SESSION['role'] ?? 'admin';
$user_id = (int)($_SESSION['user_id'] ?? 0);

// KPI queries
$total_patients = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
$total_visits_month = (int)$mysqli->query("SELECT COUNT(*) as c FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'];
$high_risk = (int)$mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3")->fetch_assoc()['c'];
$critical_lost = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1 AND patient_id NOT IN (SELECT DISTINCT patient_id FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY))")->fetch_assoc()['c'];
$avg_hba1c = $mysqli->query("SELECT ROUND(AVG(bs.hba1c_value), 1) as avg FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)")->fetch_assoc()['avg'];
$healing_rate = $mysqli->query("SELECT ROUND(COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate FROM visits v LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id LEFT JOIN outcomes o ON v.visit_id = o.visit_id WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)")->fetch_assoc()['rate'];

// Doctor-specific: my patients
if ($user_role === 'doctor' || $user_role === 'admin') {
    $my_patients_count = (int)$mysqli->query("SELECT COUNT(DISTINCT patient_id) as c FROM visits WHERE created_by = $user_id")->fetch_assoc()['c'];
    $my_visits_month = (int)$mysqli->query("SELECT COUNT(*) as c FROM visits WHERE created_by = $user_id AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'];
    $my_high_risk = (int)$mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3 AND v.created_by = $user_id")->fetch_assoc()['c'];
}
?>
<style>
    .insight-item { padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid var(--border); margin-bottom: 0.5rem; transition: var(--transition); cursor: pointer; }
    .insight-item:hover { box-shadow: var(--shadow-md); transform: translateX(-2px); }
    .insight-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .kpi-trend { font-size: 12px; padding: 2px 6px; border-radius: 4px; }
    .role-view { display: none; }
    .role-view.active { display: block; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🧠 لوحة التحكم الذكية</h1>
    <p class="page-subtitle">Smart Dashboard — رؤى وتحليلات آنية</p>
</div>

<!-- Role tabs -->
<div class="flex gap-2 mb-4 flex-wrap">
    <button class="btn btn-sm <?php echo $user_role === 'admin' ? 'btn-primary' : 'btn-secondary'; ?> role-tab" data-role="admin" onclick="switchRole('admin')">👑 إداري</button>
    <button class="btn btn-sm <?php echo $user_role === 'doctor' ? 'btn-primary' : 'btn-secondary'; ?> role-tab" data-role="doctor" onclick="switchRole('doctor')">👨‍⚕️ طبيب</button>
    <button class="btn btn-sm btn-secondary role-tab" data-role="nurse" onclick="switchRole('nurse')">🩺 تمريض</button>
</div>

<!-- Admin View -->
<div class="role-view" id="role-admin">
    <div class="stats-grid mb-4">
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div><div class="stat-value" style="color:#0a7e6e;"><?php echo $total_patients; ?></div><div class="stat-label">👥 إجمالي المرضى</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div><div class="stat-value" style="color:#1d4ed8;"><?php echo $total_visits_month; ?></div><div class="stat-label">🩺 زيارات (30 يوم)</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#dc2626,#ef4444);"></div><div class="stat-value" style="color:#dc2626;"><?php echo $high_risk; ?></div><div class="stat-label">🔴 حالات حرجة</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div><div class="stat-value" style="color:#d97706;"><?php echo $critical_lost; ?></div><div class="stat-label">🚪 منقطعون (>90 يوم)</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div><div class="stat-value" style="color:#059669;"><?php echo $healing_rate ?: '—'; ?>%</div><div class="stat-label">✅ معدل الشفاء</div></div>
    </div>

    <!-- QI Score Card -->
    <div class="card mb-4" style="background:linear-gradient(135deg,#1a3a5c,#0a2342);color:#fff;border:none;">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div style="display:flex;align-items:center;gap:1.5rem;">
                <?php
                    // Compute QI Score
                    $qi_hba1c_pts = $avg_hba1c ? max(0, min(100, round(100 - ($avg_hba1c - 5) * 20))) : 0;
                    $qi_visits_score = min($total_visits_month / 30 * 20, 20);
                    $qi_heal_score = $healing_rate ? min($healing_rate / 70 * 100, 100) : 0;
                    $qi_risk_score = $high_risk > 0 ? max(0, 100 - $high_risk * 5) : 100;
                    $qi_lost_score = $critical_lost > 0 ? max(0, 100 - $critical_lost * 2) : 100;
                    
                    $qi_smart = round($qi_hba1c_pts * 0.25 + $qi_visits_score * 0.15 + $qi_heal_score * 0.25 + $qi_risk_score * 0.20 + $qi_lost_score * 0.15, 1);
                    $qi_grade = $qi_smart >= 80 ? 'ممتاز' : ($qi_smart >= 60 ? 'جيد' : ($qi_smart >= 40 ? 'مقبول' : 'ضعيف'));
                ?>
                <div style="text-align:center;">
                    <div style="font-size:2.5rem;font-weight:900;line-height:1;"><?php echo $qi_smart; ?>%</div>
                    <div style="font-size:11px;opacity:0.85;">Smart QI</div>
                </div>
                <div>
                    <div style="font-size:1.1rem;font-weight:700;"><?php echo $qi_smart >= 80 ? '🏆' : ($qi_smart >= 60 ? '👍' : '📊'); ?> <?php echo $qi_grade; ?></div>
                    <div style="font-size:12px;opacity:0.8;">مؤشر أداء العيادة الذكي</div>
                </div>
            </div>
            <div style="display:flex;gap:0.8rem;flex-wrap:wrap;">
                <div style="text-align:center;padding:0.4rem 0.8rem;background:rgba(255,255,255,0.1);border-radius:10px;min-width:60px;">
                    <div style="font-size:1rem;font-weight:700;"><?php echo $avg_hba1c ?: '—'; ?>%</div>
                    <div style="font-size:9px;opacity:0.7;">متوسط HbA1c</div>
                </div>
                <div style="text-align:center;padding:0.4rem 0.8rem;background:rgba(255,255,255,0.1);border-radius:10px;min-width:60px;">
                    <div style="font-size:1rem;font-weight:700;"><?php echo $healing_rate ?: '—'; ?>%</div>
                    <div style="font-size:9px;opacity:0.7;">معدل الشفاء</div>
                </div>
                <div style="text-align:center;padding:0.4rem 0.8rem;background:rgba(255,255,255,0.1);border-radius:10px;min-width:60px;">
                    <div style="font-size:1rem;font-weight:700;"><?php echo $total_visits_month; ?></div>
                    <div style="font-size:9px;opacity:0.7;">زيارات / شهر</div>
                </div>
            </div>
        </div>
        <?php 
            $smart_recs = [];
            if ($avg_hba1c && $avg_hba1c > 8) $smart_recs[] = ['icon' => '🩸', 'text' => "متوسط HbA1c مرتفع ({$avg_hba1c}%) — مراجعة خطط العلاج", 'type' => 'critical'];
            if ($healing_rate && $healing_rate < 50) $smart_recs[] = ['icon' => '🩹', 'text' => "معدل الشفاء منخفض ({$healing_rate}%) — تحسين بروتوكولات العناية", 'type' => 'warning'];
            if ($high_risk > 5) $smart_recs[] = ['icon' => '🔴', 'text' => "{$high_risk} حالة حرجة — تكثيف المتابعة", 'type' => 'critical'];
            if ($critical_lost > 10) $smart_recs[] = ['icon' => '🚪', 'text' => "{$critical_lost} مريض منقطع — حملة استدعاء", 'type' => 'warning'];
        ?>
        <?php if (!empty($smart_recs)): ?>
        <div style="margin-top:0.8rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php foreach ($smart_recs as $sr): ?>
            <span style="background:rgba(255,255,255,0.12);padding:4px 10px;border-radius:20px;font-size:11px;">
                <?php echo $sr['icon']; ?> <?php echo $sr['text']; ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Monthly trend chart -->
    <div class="card mb-4">
        <div class="card-header"><div class="card-title">📈 اتجاه الزيارات الشهرية</div></div>
        <div style="height:220px;"><canvas id="trendChart" width="800" height="220"></canvas></div>
    </div>
</div>

<!-- Doctor View -->
<div class="role-view" id="role-doctor">
    <div class="stats-grid mb-4">
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div><div class="stat-value" style="color:#0a7e6e;"><?php echo $my_patients_count ?? '—'; ?></div><div class="stat-label">👥 مرضاي</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div><div class="stat-value" style="color:#1d4ed8;"><?php echo $my_visits_month ?? '—'; ?></div><div class="stat-label">📅 زياراتي (30 يوم)</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#dc2626,#ef4444);"></div><div class="stat-value" style="color:#dc2626;"><?php echo $my_high_risk ?? '—'; ?></div><div class="stat-label">🔴 مرضاي حرجين</div></div>
        <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div><div class="stat-value" style="color:#d97706;"><?php echo $healing_rate ?: '—'; ?>%</div><div class="stat-label">✅ معدل شفاء العيادة</div></div>
    </div>

    <!-- My high-risk patients -->
    <div class="card mb-4">
        <div class="card-header"><div class="card-title">🔴 مرضاي الأكثر خطورة</div></div>
        <div id="myHighRiskPatients" style="max-height:300px;overflow-y:auto;"><p class="text-muted text-center" style="padding:2rem;">جاري التحميل...</p></div>
    </div>
</div>

<!-- Nurse View -->
<div class="role-view" id="role-nurse">
    <div class="card mb-4">
        <div class="card-header"><div class="card-title">📋 مهام اليوم</div></div>
        <div id="nurseTasks">
            <p class="text-muted text-center" style="padding:2rem;">جاري التحميل...</p>
        </div>
    </div>
</div>

<!-- Insights Feed (shared across all views) -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">💡 رؤى ذكية — Automated Insights</div></div>
    <div id="insightsFeed"><p class="text-muted text-center" style="padding:2rem;">جاري تحليل البيانات...</p></div>
</div>

<!-- Quick Actions -->
<div class="flex flex-wrap gap-2 mb-4">
    <a href="<?php echo BASE_URL; ?>/modules/analytics/anomaly_detection.php" class="btn btn-primary">🔍 كشف الشذوذ</a>
    <a href="<?php echo BASE_URL; ?>/modules/analytics/risk_prediction.php" class="btn btn-secondary">🔮 التنبؤ بالمخاطر</a>
    <a href="<?php echo BASE_URL; ?>/modules/analytics/specialist_analytics.php" class="btn btn-secondary">🔬 تحليلات متخصصة</a>
    <a href="<?php echo BASE_URL; ?>/modules/analytics/report_builder.php" class="btn btn-secondary">📊 منشئ التقارير</a>
</div>

</div></div></div>

<script>
function switchRole(role) {
    document.querySelectorAll('.role-view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('btn-primary'));
    document.querySelectorAll('.role-tab').forEach(t => t.classList.add('btn-secondary'));
    const view = document.getElementById('role-' + role);
    if (view) view.classList.add('active');
    const tab = document.querySelector(`.role-tab[data-role="${role}"]`);
    if (tab) { tab.classList.remove('btn-secondary'); tab.classList.add('btn-primary'); }
}

// Show current user's role by default
document.addEventListener('DOMContentLoaded', function() {
    switchRole('<?php echo $user_role === 'admin' ? 'admin' : ($user_role === 'doctor' ? 'doctor' : 'nurse'); ?>');

    // Load monthly trend chart
    fetch(BASE_URL + '/api/analytics.php?action=monthly_trends')
        .then(r => r.json())
        .then(data => {
            if (data && data.length > 0) {
                const chart = new ClinicChart('trendChart');
                chart.drawLineChart(
                    data.map(d => d.month.slice(5) + '/' + d.month.slice(0,4)),
                    data.map(d => parseInt(d.cnt)),
                    { lineColor: '#3b82f6', fillColor: 'rgba(59,130,246,0.1)' }
                );
            }
        })
        .catch(() => {});

    // Load insights
    fetch(BASE_URL + '/api/analytics.php?action=insights')
        .then(r => r.json())
        .then(data => {
            const feed = document.getElementById('insightsFeed');
            if (!data || data.length === 0) {
                feed.innerHTML = '<p class="text-muted text-center">لا توجد رؤى كافية بعد</p>';
                return;
            }
            const colors = { improvement: '#d1fae5', warning: '#fee2e2', alert: '#fef3c7', info: '#eff6ff' };
            const borders = { improvement: '#059669', warning: '#dc2626', alert: '#d97706', info: '#1d4ed8' };
            feed.innerHTML = data.map(i => `
                <div class="insight-item" style="border-right: 3px solid ${borders[i.type] || '#e2e8f0'};background:${colors[i.type] || '#fff'};">
                    <div class="flex items-center gap-3">
                        <span style="font-size:1.5rem;">${i.icon}</span>
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:14px;">${i.text}</div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                ${i.trend === 'up' ? '📈 اتجاه إيجابي' : i.trend === 'down' ? '📉 يحتاج انتباه' : '➖ مستقر'}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        })
        .catch(() => {});

    // Load anomalies count for doctor view
    fetch(BASE_URL + '/api/analytics.php?action=anomalies')
        .then(r => r.json())
        .then(data => {
            if (data && data.length > 0) {
                const container = document.getElementById('myHighRiskPatients');
                if (container) {
                    const critical = data.filter(a => a.severity === 'critical' || a.severity === 'high').slice(0, 5);
                    if (critical.length > 0) {
                        container.innerHTML = critical.map(a => `
                            <div class="flex items-center gap-2" style="padding:6px;border-bottom:1px solid var(--border);font-size:13px;">
                                <span>${a.icon}</span>
                                <a href="${BASE_URL}/modules/patients/view.php?id=${a.patient_id}" style="flex:1;font-weight:600;">${a.patient_name}</a>
                                <span class="badge badge-danger" style="font-size:10px;">${a.type === 'slow_healing' ? 'تأخر التئام' : a.type === 'wagner_worsening' ? 'تدهور Wagner' : a.type === 'hba1c_spike' ? 'ارتفاع HbA1c' : a.type}</span>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<p class="text-muted text-center" style="padding:1rem;">✅ لا توجد حالات حرجة</p>';
                    }
                }
            }
        })
        .catch(() => {});

    // Nurse tasks
    const nurseContainer = document.getElementById('nurseTasks');
    if (nurseContainer) {
        nurseContainer.innerHTML = `
            <div class="flex items-center gap-2" style="padding:8px;border-bottom:1px solid var(--border);">
                <span>📋</span>
                <div style="flex:1;"><strong>جولات اليوم</strong> — تفقد جروح المرضى</div>
                <span class="badge badge-info">3 مرضى</span>
            </div>
            <div class="flex items-center gap-2" style="padding:8px;border-bottom:1px solid var(--border);">
                <span>🩹</span>
                <div style="flex:1;"><strong>تغيير ضمادات</strong> — للحالات المقررة</div>
                <span class="badge badge-warning">2 مجدول</span>
            </div>
            <div class="flex items-center gap-2" style="padding:8px;">
                <span>📞</span>
                <div style="flex:1;"><strong>متابعة منقطعين</strong> — استدعاء مرضى لم يحضروا</div>
                <span class="badge badge-danger"><?php echo $critical_lost; ?></span>
            </div>
        `;
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
