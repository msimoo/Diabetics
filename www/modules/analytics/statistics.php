<?php
/**
 * Clinic Statistics & Analytics
 */
$page_title = 'الإحصائيات | Statistics';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// === GATHER STATISTICS ===

// 1. Overall counts
$total_patients = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
$total_visits = $mysqli->query("SELECT COUNT(*) as c FROM visits")->fetch_assoc()['c'];
$total_assessments = $mysqli->query("SELECT COUNT(*) as c FROM foot_assessments")->fetch_assoc()['c'];
$total_ulcers = $mysqli->query("SELECT COUNT(*) as c FROM foot_ulcers")->fetch_assoc()['c'];

// 2. Diabetes type distribution
$diabetes_data = [];
$result = $mysqli->query("SELECT COALESCE(diabetes_type, 'غير محدد') as type, COUNT(*) as cnt FROM medical_history GROUP BY diabetes_type ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) {
    $diabetes_data[] = $row;
}

// 3. Wagner grade distribution
$wagner_data = [];
$result = $mysqli->query("SELECT COALESCE(wagner_grade, -1) as grade, COUNT(*) as cnt FROM foot_assessments GROUP BY wagner_grade ORDER BY grade");
while ($row = $result->fetch_assoc()) {
    $wagner_data[] = $row;
}

// 4. Healed vs active cases
$healed = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN outcomes o ON v.visit_id = o.visit_id WHERE o.improvement_percentage = 100")->fetch_assoc()['c'];
$active_wounds = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_ulcers fu ON v.visit_id = fu.visit_id LEFT JOIN outcomes o ON v.visit_id = o.visit_id WHERE (o.improvement_percentage IS NULL OR o.improvement_percentage < 100)")->fetch_assoc()['c'];

// 5. Common causes of injury
$causes = [];
$result = $mysqli->query("SELECT initial_cause, COUNT(*) as cnt FROM foot_ulcers WHERE initial_cause IS NOT NULL AND initial_cause != '' GROUP BY initial_cause ORDER BY cnt DESC LIMIT 6");
while ($row = $result->fetch_assoc()) {
    $causes[] = $row;
}

// 6. Monthly visits (last 6 months)
$monthly_visits = [];
$result = $mysqli->query("SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as cnt FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_visits[] = $row;
}

// 7. Risk summary
$high_risk = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3")->fetch_assoc()['c'];
$medium_risk = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade = 2")->fetch_assoc()['c'];
$low_risk = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade <= 1")->fetch_assoc()['c'];

// 8. Average healing time (days)
$healing_time = $mysqli->query("SELECT AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_days FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id WHERE o.healing_date IS NOT NULL")->fetch_assoc()['avg_days'];

// 9. Gender distribution
$male = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'ذكر' AND is_active = 1")->fetch_assoc()['c'];
$female = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'أنثى' AND is_active = 1")->fetch_assoc()['c'];

// 10. Amputation rate
$total_amputations = $mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE current_amputation IS NOT NULL AND current_amputation != 'لا'")->fetch_assoc()['c'];

// === NEW: ENHANCED ANALYTICS ===

// 11. Age distribution
$age_dist = [];
$result = $mysqli->query("SELECT 
    CASE 
        WHEN age < 18 THEN 'أقل من 18'
        WHEN age BETWEEN 18 AND 30 THEN '18-30'
        WHEN age BETWEEN 31 AND 45 THEN '31-45'
        WHEN age BETWEEN 46 AND 60 THEN '46-60'
        WHEN age BETWEEN 61 AND 75 THEN '61-75'
        ELSE 'أكثر من 75'
    END as age_group, COUNT(*) as cnt
    FROM patients WHERE is_active = 1 GROUP BY age_group ORDER BY MIN(age)");
while ($row = $result->fetch_assoc()) {
    $age_dist[] = $row;
}

// 12. City distribution
$city_dist = [];
$result = $mysqli->query("SELECT COALESCE(city, 'غير محدد') as city, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY city ORDER BY cnt DESC LIMIT 6");
while ($row = $result->fetch_assoc()) {
    $city_dist[] = $row;
}

// 13. Nationality distribution
$nation_dist = [];
$result = $mysqli->query("SELECT COALESCE(nationality, 'غير محدد') as nationality, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY nationality ORDER BY cnt DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $nation_dist[] = $row;
}

// 14. Period-over-period comparison
$current_month_visits = $mysqli->query("SELECT COUNT(*) as c FROM visits WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())")->fetch_assoc()['c'];
$prev_month_visits = $mysqli->query("SELECT COUNT(*) as c FROM visits WHERE MONTH(visit_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(visit_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetch_assoc()['c'];
$visit_change = $prev_month_visits > 0 ? round(($current_month_visits - $prev_month_visits) / $prev_month_visits * 100) : 0;

// 15. Top doctors by visits
$top_doctors = [];
$result = $mysqli->query("SELECT u.full_name, COUNT(v.visit_id) as visit_count 
    FROM visits v JOIN users u ON v.created_by = u.user_id 
    GROUP BY u.user_id ORDER BY visit_count DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $top_doctors[] = $row;
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">📈 إحصائيات العيادة</h1><p class="page-subtitle">Clinic Analytics Dashboard</p></div>

<!-- KPI Cards -->
<div class="stats-grid">
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div>
        <div class="stat-icon" style="background:#e0f5f2;color:#0a7e6e;">👥</div>
        <div class="stat-value" style="color:#0a7e6e;"><?php echo $total_patients; ?></div>
        <div class="stat-label">إجمالي المرضى</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
        <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">🩺</div>
        <div class="stat-value" style="color:#1d4ed8;"><?php echo $total_visits; ?></div>
        <div class="stat-label">إجمالي الزيارات</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
        <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;">🦶</div>
        <div class="stat-value" style="color:#7c3aed;"><?php echo $total_assessments; ?></div>
        <div class="stat-label">تقييمات القدم</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#ec4899,#db2777);"></div>
        <div class="stat-icon" style="background:#fdf2f8;color:#ec4899;">🩹</div>
        <div class="stat-value" style="color:#db2777;"><?php echo $total_ulcers; ?></div>
        <div class="stat-label">جروح مسجلة</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
        <div class="stat-icon" style="background:#ecfdf5;color:#10b981;">✅</div>
        <div class="stat-value" style="color:#059669;"><?php echo $healed; ?></div>
        <div class="stat-label">تم شفاؤها</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#ef4444,#dc2626);"></div>
        <div class="stat-icon" style="background:#fef2f2;color:#ef4444;">🆘</div>
        <div class="stat-value" style="color:#dc2626;"><?php echo $high_risk; ?></div>
        <div class="stat-label">حالات خطرة</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
        <div class="stat-icon" style="background:#fffbeb;color:#f59e0b;">🦿</div>
        <div class="stat-value" style="color:#d97706;"><?php echo $total_amputations; ?></div>
        <div class="stat-label">حالات بتر</div>
    </div>
    <div class="stat-card card-fade-in"><div class="stat-bar" style="background:linear-gradient(90deg,#06b6d4,#0891b2);"></div>
        <div class="stat-icon" style="background:#ecfeff;color:#06b6d4;">📅</div>
        <div class="stat-value" style="color:#0891b2;"><?php echo $healing_time ? round($healing_time) . ' يوم' : '—'; ?></div>
        <div class="stat-label">متوسط وقت الشفاء</div>
    </div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Diabetes Distribution -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">📊 توزيع أنواع السكري</div></div>
        <div style="height:240px;"><canvas id="diabetesChart" width="400" height="240"></canvas></div>
    </div>
    <!-- Wagner Distribution -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">📊 توزيع درجة Wagner</div></div>
        <div style="height:240px;"><canvas id="wagnerChart" width="400" height="240"></canvas></div>
    </div>
    <!-- Common Causes -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">⚠️ أسباب الإصابة الشائعة</div></div>
        <?php if ($causes): ?>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($causes as $c): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:13px;"><?php echo $c['initial_cause']; ?></span>
                <span class="badge badge-info"><?php echo $c['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:#94a3b8;text-align:center;padding:20px;">لا توجد بيانات كافية</p>
        <?php endif; ?>
    </div>
</div>

<!-- Enhanced Demographics -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">👤 توزيع الأعمار</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <?php foreach ($age_dist as $a): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
                <span><?php echo $a['age_group']; ?></span>
                <span class="badge badge-info"><?php echo $a['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">🏙️ توزيع المدن</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <?php foreach ($city_dist as $c): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
                <span><?php echo escape_output($c['city']); ?></span>
                <span class="badge badge-info"><?php echo $c['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">🌍 الجنسيات</div></div>
        <div style="display:flex;flex-wrap:wrap;gap:4px;">
            <?php foreach ($nation_dist as $n): ?>
            <span class="badge badge-info"><?php echo escape_output($n['nationality']); ?> (<?php echo $n['cnt']; ?>)</span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">📈 مقارنة شهرية</div></div>
        <div style="text-align:center;padding:10px;">
            <div style="font-size:28px;font-weight:800;color:<?php echo $visit_change >= 0 ? 'var(--green)' : 'var(--red)'; ?>">
                <?php echo $visit_change >= 0 ? '↑' : '↓'; ?> <?php echo abs($visit_change); ?>%
            </div>
            <div style="font-size:13px;color:var(--gray);">تغير الزيارات (الشهر الحالي vs السابق)</div>
            <div style="margin-top:8px;display:flex;justify-content:center;gap:20px;font-size:13px;">
                <span>هذا الشهر: <strong><?php echo $current_month_visits; ?></strong></span>
                <span>السابق: <strong><?php echo $prev_month_visits; ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Top Doctors -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">👨‍⚕️ أفضل الأطباء (حسب عدد الزيارات)</div></div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <?php foreach ($top_doctors as $i => $d): ?>
        <div style="flex:1;min-width:120px;text-align:center;padding:12px;background:var(--teal-pale);border-radius:10px;">
            <div style="font-size:24px;">🥇<?php echo $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '')); ?></div>
            <div style="font-weight:700;font-size:14px;"><?php echo escape_output($d['full_name']); ?></div>
            <div style="font-size:20px;font-weight:800;color:var(--teal);"><?php echo $d['visit_count']; ?></div>
            <div style="font-size:11px;color:var(--gray);">زيارة</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Quick Data Sheet -->
<div class="card">
    <div class="card-header"><div class="card-title">ورقة بيانات سريعة</div></div>
    <table>
        <thead><tr><th>المؤشر</th><th>القيمة</th></tr></thead>
        <tbody>
            <tr><td>إجمالي المرضى (نشط)</td><td><strong><?php echo $total_patients; ?></strong></td></tr>
            <tr><td>ذكور / إناث</td><td><strong><?php echo $male; ?></strong> / <strong><?php echo $female; ?></strong></td></tr>
            <tr><td>إجمالي الزيارات</td><td><strong><?php echo $total_visits; ?></strong></td></tr>
            <tr><td>جروح نشطة (لم تلتئم)</td><td><strong style="color:#ef4444;"><?php echo $active_wounds; ?></strong></td></tr>
            <tr><td>تم شفاؤها كلياً</td><td><strong style="color:#10b981;"><?php echo $healed; ?></strong></td></tr>
            <tr><td>حالات بتر</td><td><strong style="color:#dc2626;"><?php echo $total_amputations; ?></strong></td></tr>
            <tr><td>متوسط وقت الشفاء</td><td><strong><?php echo $healing_time ? round($healing_time) . ' يوم' : '—'; ?></strong></td></tr>
            <tr><td>تقييمات القدم</td><td><strong><?php echo $total_assessments; ?></strong></td></tr>
        </tbody>
    </table>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Diabetes chart
    <?php if ($diabetes_data): ?>
    const diabetesData = <?php echo json_encode($diabetes_data, JSON_UNESCAPED_UNICODE); ?>;
    const dc = new ClinicChart('diabetesChart');
    dc.drawPieChart(diabetesData.map(d => ({ label: d.type, value: parseInt(d.cnt) })), { donut: true });
    <?php endif; ?>

    // Wagner chart
    <?php if ($wagner_data): ?>
    const wagnerData = <?php echo json_encode($wagner_data, JSON_UNESCAPED_UNICODE); ?>;
    const wc = new ClinicChart('wagnerChart');
    wc.drawBarChart(
        wagnerData.map(d => d.grade == -1 ? 'غير محدد' : 'W' + d.grade),
        wagnerData.map(d => parseInt(d.cnt)),
        { colors: ['#10b981', '#f59e0b', '#f97316', '#ef4444', '#dc2626', '#7f1d1d'] }
    );
    <?php endif; ?>
});
</script>
</div>
