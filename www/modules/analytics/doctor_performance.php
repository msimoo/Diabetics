<?php
/**
 * Doctor Performance & Benchmarking
 */
$page_title = '👨‍⚕️ أداء الأطباء | Doctor Performance';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// Doctor performance metrics
$doctors = [];
$result = $mysqli->query("
    SELECT 
        u.user_id, u.full_name, u.role,
        COUNT(DISTINCT v.visit_id) as total_visits,
        COUNT(DISTINCT v.patient_id) as unique_patients,
        COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.visit_id END) as foot_exams,
        COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) as healed,
        ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
        ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days,
        (SELECT COUNT(*) FROM visits v2 WHERE v2.created_by = u.user_id AND v2.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as month_visits,
        (SELECT COUNT(*) FROM visits v2 WHERE v2.created_by = u.user_id AND v2.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_visits
    FROM users u
    LEFT JOIN visits v ON u.user_id = v.created_by AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE u.is_active = 1 AND u.role IN ('doctor', 'admin')
    GROUP BY u.user_id
    HAVING total_visits > 0
    ORDER BY total_visits DESC
");
while ($row = $result->fetch_assoc()) { $doctors[] = $row; }

// Clinic averages for benchmarking
$clinic_avg = $mysqli->query("
    SELECT 
        ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
        ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days,
        ROUND(COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT v.patient_id), 0) * 100, 1) as healing_rate
    FROM visits v
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// Monthly trends per doctor
$monthly_doctor = [];
$result = $mysqli->query("
    SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as month, u.full_name, COUNT(*) as cnt
    FROM visits v JOIN users u ON v.created_by = u.user_id
    WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month, u.user_id
    ORDER BY month
");
while ($row = $result->fetch_assoc()) { $monthly_doctor[] = $row; }
?>
<style>
    .doctor-rank { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; }
    .rank-1 { background: linear-gradient(135deg,#f59e0b,#d97706); color: #fff; }
    .rank-2 { background: linear-gradient(135deg,#94a3b8,#64748b); color: #fff; }
    .rank-3 { background: linear-gradient(135deg,#b45309,#92400e); color: #fff; }
    .doctor-card { padding: 1rem; border-radius: 12px; border: 1px solid var(--border); transition: var(--transition); }
    .doctor-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
    .metric-compare { display: flex; align-items: center; gap: 6px; font-size: 12px; }
    .metric-up { color: var(--green); }
    .metric-down { color: var(--red); }
    .metric-same { color: var(--text-muted); }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="page-title">👨‍⚕️ أداء الأطباء</h1><p class="page-subtitle">Doctor Performance & Benchmarking</p></div>
    <form method="get" class="flex gap-2 items-center">
        <input type="date" name="from_date" value="<?php echo $from_date; ?>" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);font-size:12px;font-family:'Tajawal',sans-serif;background:var(--bg-input);color:var(--text-body);">
        <input type="date" name="to_date" value="<?php echo $to_date; ?>" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);font-size:12px;font-family:'Tajawal',sans-serif;background:var(--bg-input);color:var(--text-body);">
        <button type="submit" class="btn btn-sm btn-primary">تحديث</button>
    </form>
</div>

<!-- Clinic Averages -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,var(--teal),var(--teal-light));"></div>
        <div class="stat-value" style="color:var(--teal);font-size:1.3rem;"><?php echo count($doctors); ?></div><div class="stat-label">الأطباء النشطين</div></div>
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
        <div class="stat-value" style="color:#1d4ed8;font-size:1.3rem;"><?php echo $clinic_avg['avg_improvement'] ?: '—'; ?>%</div><div class="stat-label">متوسط التحسن</div></div>
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
        <div class="stat-value" style="color:#059669;font-size:1.3rem;"><?php echo $clinic_avg['healing_rate'] ?: '—'; ?>%</div><div class="stat-label">معدل الشفاء</div></div>
    <div class="stat-card"><div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
        <div class="stat-value" style="color:#d97706;font-size:1.3rem;"><?php echo $clinic_avg['avg_healing_days'] ?: '—'; ?></div><div class="stat-label">متوسط أيام الشفاء</div></div>
</div>

<!-- Doctor Cards -->
<div class="card">
    <div class="card-header"><div class="card-title">🏆 ترتيب الأطباء</div></div>
    <?php foreach ($doctors as $i => $d): 
        $rank_class = $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : ''));
        $bg = $i === 0 ? 'background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#f59e0b;' : '';
        $compared_healing = $d['avg_improvement'] && $clinic_avg['avg_improvement'] ? $d['avg_improvement'] - $clinic_avg['avg_improvement'] : 0;
        $healing_rate = $d['unique_patients'] > 0 ? round($d['healed'] / $d['unique_patients'] * 100, 1) : 0;
    ?>
    <div class="doctor-card mb-2" style="<?php echo $bg; ?>">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="doctor-rank <?php echo $rank_class; ?>"><?php echo $i + 1; ?></div>
            <div style="flex:1;min-width:150px;">
                <div style="font-weight:700;font-size:1rem;"><?php echo escape_output($d['full_name']); ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo $d['role']; ?> | 📅 هذا الأسبوع: <?php echo $d['week_visits']; ?> زيارة</div>
            </div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <div style="text-align:center;min-width:50px;">
                    <div style="font-weight:800;font-size:1.1rem;color:var(--teal);"><?php echo $d['total_visits']; ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">زيارة</div>
                </div>
                <div style="text-align:center;min-width:50px;">
                    <div style="font-weight:800;font-size:1.1rem;color:var(--blue);"><?php echo $d['unique_patients']; ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">مريض</div>
                </div>
                <div style="text-align:center;min-width:50px;">
                    <div style="font-weight:800;font-size:1.1rem;color:var(--green);"><?php echo $healing_rate; ?>%</div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">شفاء</div>
                </div>
                <div style="text-align:center;min-width:50px;">
                    <div style="font-weight:800;font-size:1.1rem;color:var(--orange);"><?php echo $d['avg_improvement'] ? $d['avg_improvement'] . '%' : '—'; ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">تحسن</div>
                </div>
                <div style="text-align:center;min-width:60px;">
                    <div style="font-weight:800;font-size:1.1rem;color:var(--gold);"><?php echo $d['foot_exams']; ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">فحص قدم</div>
                </div>
            </div>
            <?php if ($compared_healing != 0): ?>
            <div class="metric-compare">
                <span class="<?php echo $compared_healing > 0 ? 'metric-up' : 'metric-down'; ?>">
                    <?php echo $compared_healing > 0 ? '▲' : '▼'; ?> <?php echo abs($compared_healing); ?>%
                </span>
                <span style="color:var(--text-muted);">vs المعدل</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Monthly Trend Chart -->
<div class="card mt-4">
    <div class="card-header"><div class="card-title">📈 اتجاه الزيارات الشهرية</div></div>
    <div style="height:250px;"><canvas id="doctorTrendChart" width="900" height="250"></canvas></div>
</div>

</div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthData = <?php echo json_encode($monthly_doctor, JSON_UNESCAPED_UNICODE); ?>;
    if (monthData.length > 0) {
        const months = [...new Set(monthData.map(d => d.month))].sort();
        const doctors = [...new Set(monthData.map(d => d.full_name))];
        const colors = ['#0a7e6e', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#10b981'];
        
        // Use a simple stacked approach - draw as separate lines
        const values = months.map(m => {
            return monthData.filter(d => d.month === m).reduce((sum, d) => sum + parseInt(d.cnt), 0);
        });
        
        if (values.length > 0) {
            const chart = new ClinicChart('doctorTrendChart');
            chart.drawBarChart(
                months.map(m => m.slice(5) + '/' + m.slice(0,4)),
                values,
                { colors: ['#0a7e6e'] }
            );
        }
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
