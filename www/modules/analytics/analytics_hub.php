<?php
/**
 * Analytics Hub - Comprehensive Analytics Dashboard
 * Central analytics page with all charts, date filtering, and CSV export
 */
$page_title = 'مركز التحليلات | Analytics Hub';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Date filtering
$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analytics_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    // Fetch data
    $patients = $mysqli->query("SELECT patient_id, file_number, full_name, gender, age, city, nationality, created_at FROM patients WHERE is_active = 1 ORDER BY created_at DESC");
    fputcsv($output, ['رقم الملف', 'الاسم', 'الجنس', 'العمر', 'المدينة', 'الجنسية', 'تاريخ التسجيل']);
    while ($p = $patients->fetch_assoc()) {
        fputcsv($output, [$p['file_number'], $p['full_name'], $p['gender'], $p['age'], $p['city'], $p['nationality'], $p['created_at']]);
    }
    fclose($output);
    exit;
}

// ===== DEMOGRAPHICS =====

// 1. Age distribution
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

// 2. Gender distribution
$male = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'ذكر' AND is_active = 1")->fetch_assoc()['c'];
$female = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'أنثى' AND is_active = 1")->fetch_assoc()['c'];

// 3. City distribution
$city_dist = [];
$result = $mysqli->query("SELECT COALESCE(city, 'غير محدد') as city, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY city ORDER BY cnt DESC LIMIT 8");
while ($row = $result->fetch_assoc()) {
    $city_dist[] = $row;
}

// 4. Nationality distribution
$nation_dist = [];
$result = $mysqli->query("SELECT COALESCE(nationality, 'غير محدد') as nationality, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY nationality ORDER BY cnt DESC LIMIT 6");
while ($row = $result->fetch_assoc()) {
    $nation_dist[] = $row;
}

// ===== ENHANCED TRENDS =====

// 5. Monthly visits (full range)
$monthly_visits = [];
$result = $mysqli->query("SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as cnt 
    FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_visits[] = $row;
}

// 6. Monthly new patients
$monthly_new = [];
$result = $mysqli->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt 
    FROM patients WHERE created_at BETWEEN '$from_date' AND '$to_date' AND is_active = 1
    GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_new[] = $row;
}

// 7. Monthly healing outcomes
$monthly_healed = [];
$result = $mysqli->query("SELECT DATE_FORMAT(o.improvement_date, '%Y-%m') as month, COUNT(*) as cnt 
    FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id 
    WHERE o.improvement_date BETWEEN '$from_date' AND '$to_date' AND o.improvement_percentage >= 75
    GROUP BY DATE_FORMAT(o.improvement_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_healed[] = $row;
}

// 8. Monthly amputations
$monthly_amps = [];
$result = $mysqli->query("SELECT DATE_FORMAT(o.current_amputation_date, '%Y-%m') as month, COUNT(*) as cnt 
    FROM outcomes o WHERE o.current_amputation_date BETWEEN '$from_date' AND '$to_date' 
    AND o.current_amputation IS NOT NULL AND o.current_amputation != 'لا'
    GROUP BY DATE_FORMAT(o.current_amputation_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_amps[] = $row;
}

// ===== OUTCOME STATS =====

// 9. Wagner distribution
$wagner_dist = [];
$result = $mysqli->query("SELECT COALESCE(wagner_grade, -1) as grade, COUNT(*) as cnt FROM foot_assessments GROUP BY wagner_grade ORDER BY grade");
while ($row = $result->fetch_assoc()) {
    $wagner_dist[] = $row;
}

// 10. Healing outcomes distribution
$outcome_dist = [
    ['status' => 'التئام كامل (100%)', 'cnt' => $mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE improvement_percentage = 100")->fetch_assoc()['c']],
    ['status' => 'تحسن كبير (75%)', 'cnt' => $mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE improvement_percentage = 75")->fetch_assoc()['c']],
    ['status' => 'تحسن متوسط (50%)', 'cnt' => $mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE improvement_percentage = 50")->fetch_assoc()['c']],
    ['status' => 'تحسن طفيف (25%)', 'cnt' => $mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE improvement_percentage = 25")->fetch_assoc()['c']],
    ['status' => 'لا تحسن (0%)', 'cnt' => $mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE improvement_percentage = 0")->fetch_assoc()['c']],
];

// 11. Average healing time (days) by cause
$healing_by_cause = [];
$result = $mysqli->query("SELECT fu.initial_cause, AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_days, COUNT(*) as case_count
    FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id 
    JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    WHERE o.healing_date BETWEEN '$from_date' AND '$to_date' 
    AND fu.initial_cause IS NOT NULL AND fu.initial_cause != ''
    GROUP BY fu.initial_cause ORDER BY avg_days");
while ($row = $result->fetch_assoc()) {
    $healing_by_cause[] = $row;
}

// 12. Key KPIs
$total_patients = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
$total_visits_range = $mysqli->query("SELECT COUNT(*) as c FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];
$avg_hba1c = $mysqli->query("SELECT AVG(hba1c_value) as avg FROM blood_sugar_readings WHERE hba1c_value IS NOT NULL")->fetch_assoc()['avg'];
$bmi_avg = $mysqli->query("SELECT AVG(bmi) as avg FROM vital_signs WHERE bmi IS NOT NULL")->fetch_assoc()['avg'];
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <!-- Header -->
            <div class="page-header flex flex-wrap gap-3 items-center justify-between">
                <div>
                    <h1 class="page-title">📊 مركز التحليلات</h1>
                    <p class="page-subtitle">Analytics Hub — Comprehensive Clinic Data</p>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <!-- Date Range Filter -->
                    <form method="get" class="flex flex-wrap gap-2 items-center">
                        <label style="font-size:12px;color:var(--gray);">من</label>
                        <input type="date" name="from_date" value="<?php echo $from_date; ?>" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:'Tajawal',sans-serif;">
                        <label style="font-size:12px;color:var(--gray);">إلى</label>
                        <input type="date" name="to_date" value="<?php echo $to_date; ?>" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:'Tajawal',sans-serif;">
                        <button type="submit" class="btn btn-sm btn-primary">تحديث</button>
                    </form>
                    <a href="?export=csv&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-sm btn-secondary">📥 CSV</a>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="stats-grid">
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div>
                    <div class="stat-icon" style="background:#e0f5f2;color:#0a7e6e;">👥</div>
                    <div class="stat-value" style="color:#0a7e6e;"><?php echo $total_patients; ?></div>
                    <div class="stat-label">إجمالي المرضى</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
                    <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">🩺</div>
                    <div class="stat-value" style="color:#1d4ed8;"><?php echo $total_visits_range; ?></div>
                    <div class="stat-label">زيارات (الفترة)</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#ec4899,#db2777);"></div>
                    <div class="stat-icon" style="background:#fdf2f8;color:#ec4899;">🩸</div>
                    <div class="stat-value" style="color:#db2777;"><?php echo $avg_hba1c ? number_format($avg_hba1c, 1) . '%' : '—'; ?></div>
                    <div class="stat-label">متوسط HbA1c</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
                    <div class="stat-icon" style="background:#fffbeb;color:#f59e0b;">⚖️</div>
                    <div class="stat-value" style="color:#d97706;"><?php echo $bmi_avg ? number_format($bmi_avg, 1) : '—'; ?></div>
                    <div class="stat-label">متوسط BMI</div>
                </div>
            </div>

            <!-- Row 1: Demographics -->
            <div class="flex flex-wrap gap-4 mb-4">
                <!-- Age Distribution -->
                <div class="card" style="flex:1;min-width:250px;">
                    <div class="card-header"><div class="card-title">📊 توزيع الأعمار</div></div>
                    <div style="height:220px;"><canvas id="ageChart" width="400" height="220"></canvas></div>
                    <?php if (empty($age_dist)): ?><p style="color:#94a3b8;text-align:center;">لا توجد بيانات</p><?php endif; ?>
                </div>
                <!-- Gender Distribution -->
                <div class="card" style="flex:1;min-width:200px;">
                    <div class="card-header"><div class="card-title">👤 الجنس</div></div>
                    <div style="height:220px;"><canvas id="genderChart" width="400" height="220"></canvas></div>
                </div>
                <!-- City Distribution -->
                <div class="card" style="flex:1;min-width:250px;">
                    <div class="card-header"><div class="card-title">🏙️ المدن</div></div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <?php foreach ($city_dist as $c): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:13px;"><?php echo escape_output($c['city']); ?></span>
                            <span class="badge badge-info"><?php echo $c['cnt']; ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($city_dist)): ?><p style="color:#94a3b8;">لا توجد بيانات</p><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Row 2: Monthly Trends -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title">📈 الاتجاهات الشهرية</div>
                    <div class="flex gap-2">
                        <span style="font-size:12px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#3b82f6;"></span> زيارات</span>
                        <span style="font-size:12px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;"></span> مرضى جدد</span>
                        <span style="font-size:12px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span> شفاء</span>
                    </div>
                </div>
                <div style="height:280px;"><canvas id="trendsChart" width="900" height="280"></canvas></div>
            </div>

            <!-- Row 3: Wagner + Outcomes -->
            <div class="flex flex-wrap gap-4 mb-4">
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">📊 توزيع Wagner</div></div>
                    <div style="height:220px;"><canvas id="wagnerChartHub" width="400" height="220"></canvas></div>
                </div>
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">📊 نتائج الشفاء</div></div>
                    <div style="height:220px;"><canvas id="outcomeChart" width="400" height="220"></canvas></div>
                </div>
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">⏱️ سرعة الشفاء حسب السبب</div></div>
                    <?php if ($healing_by_cause): ?>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <?php foreach ($healing_by_cause as $h): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:12px;"><?php echo escape_output($h['initial_cause']); ?></span>
                            <span style="font-size:12px;"><strong><?php echo round($h['avg_days']); ?> يوم</strong> (<?php echo $h['case_count']; ?>)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color:#94a3b8;">لا توجد بيانات كافية</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- === QI (Quality Improvement) Section === -->
            <?php
            // QI Score for Analytics Hub
            $qi_total_patients = $total_patients ?: 1;
            $qi_visits_per_patient = $qi_total_patients > 0 ? round($total_visits_range / $qi_total_patients, 1) : 0;
            $qi_healed_count = $outcome_dist[0]['cnt'] ?? 0;
            $qi_heal_rate = $qi_total_patients > 0 ? round(($qi_healed_count / $qi_total_patients) * 100, 1) : 0;
            
            // QI Factor 1: Healing Rate (weight 30)
            $qi_heal_score = min(30, round($qi_heal_rate / 100 * 30));
            
            // QI Factor 2: Visit regularity - visits per patient (weight 20, target: >= 3 visits/year)
            $qi_visit_score = min(20, round(min($qi_visits_per_patient, 6) / 6 * 20));
            
            // QI Factor 3: Foot exam coverage (weight 25)
            $qi_total_wagner = array_sum(array_column($wagner_dist, 'cnt'));
            $qi_wagner_coverage = $qi_total_patients > 0 ? round(min($qi_total_wagner / $qi_total_patients * 100, 100), 1) : 0;
            $qi_exam_score = min(25, round($qi_wagner_coverage / 100 * 25));
            
            // QI Factor 4: Wagner 0-1 dominance (weight 25) - earlier detection is better
            $qi_low_wagner = 0;
            foreach ($wagner_dist as $w) { if ($w['grade'] >= 0 && $w['grade'] <= 1) $qi_low_wagner += $w['cnt']; }
            $qi_low_wagner_pct = $qi_total_wagner > 0 ? round(($qi_low_wagner / $qi_total_wagner) * 100, 1) : 0;
            $qi_wagner_score = min(25, round($qi_low_wagner_pct / 100 * 25));
            
            $qi_hub_total = $qi_heal_score + $qi_visit_score + $qi_exam_score + $qi_wagner_score;
            $qi_hub_grade = $qi_hub_total >= 80 ? 'ممتاز' : ($qi_hub_total >= 60 ? 'جيد' : ($qi_hub_total >= 40 ? 'مقبول' : 'يحتاج تحسين'));
            $qi_hub_color = $qi_hub_total >= 80 ? '#10b981' : ($qi_hub_total >= 60 ? '#f59e0b' : ($qi_hub_total >= 40 ? '#f97316' : '#ef4444'));
            
            // QI Smart Insights
            $qi_insights = [];
            if ($qi_heal_rate < 50) $qi_insights[] = ['icon' => '✅', 'text' => "نسبة الشفاء {$qi_heal_rate}% — أقل من الهدف", 'type' => 'warning'];
            if ($qi_visits_per_patient < 2) $qi_insights[] = ['icon' => '📅', 'text' => "متوسط الزيارات {$qi_visits_per_patient}/مريض — تشجيع المتابعة", 'type' => 'info'];
            if ($qi_low_wagner_pct < 50) $qi_insights[] = ['icon' => '🦶', 'text' => "{$qi_low_wagner_pct}% حالات مبكرة — تحسين الكشف المبكر", 'type' => 'critical'];
            
            $qi_critical_wagner = 0;
            foreach ($wagner_dist as $w) { if ($w['grade'] >= 3) $qi_critical_wagner += $w['cnt']; }
            if ($qi_critical_wagner > 0) $qi_insights[] = ['icon' => '🆘', 'text' => "{$qi_critical_wagner} حالة Wagner ≥3 — تدخل فوري", 'type' => 'critical'];
            ?>
            
            <!-- QI Score Card -->
            <div class="card mb-4" style="background:linear-gradient(135deg, <?php echo $qi_hub_color; ?>15, var(--bg-card));border-right:4px solid <?php echo $qi_hub_color; ?>;">
                <div class="flex flex-wrap gap-4 items-center">
                    <div style="text-align:center;min-width:100px;">
                        <div style="font-size:2.2rem;font-weight:900;color:<?php echo $qi_hub_color; ?>;"><?php echo $qi_hub_total; ?>%</div>
                        <div style="font-size:0.8rem;color:var(--text-muted);">مؤشر الجودة</div>
                        <div style="display:inline-block;padding:2px 10px;border-radius:12px;background:<?php echo $qi_hub_color; ?>;color:#fff;font-size:0.7rem;font-weight:700;margin-top:4px;"><?php echo $qi_hub_grade; ?></div>
                    </div>
                    <div style="flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:6px;">
                        <div style="padding:6px;background:var(--bg-input);border-radius:6px;text-align:center;">
                            <div style="font-weight:800;font-size:1rem;color:<?php echo $qi_heal_rate >= 50 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_heal_score; ?>/30</div>
                            <div style="font-size:0.65rem;color:var(--text-muted);">الشفاء</div>
                        </div>
                        <div style="padding:6px;background:var(--bg-input);border-radius:6px;text-align:center;">
                            <div style="font-weight:800;font-size:1rem;color:<?php echo $qi_visit_score >= 15 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_visit_score; ?>/20</div>
                            <div style="font-size:0.65rem;color:var(--text-muted);">المتابعة</div>
                        </div>
                        <div style="padding:6px;background:var(--bg-input);border-radius:6px;text-align:center;">
                            <div style="font-weight:800;font-size:1rem;color:<?php echo $qi_exam_score >= 18 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_exam_score; ?>/25</div>
                            <div style="font-size:0.65rem;color:var(--text-muted);">فحص القدم</div>
                        </div>
                        <div style="padding:6px;background:var(--bg-input);border-radius:6px;text-align:center;">
                            <div style="font-weight:800;font-size:1rem;color:<?php echo $qi_wagner_score >= 18 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_wagner_score; ?>/25</div>
                            <div style="font-size:0.65rem;color:var(--text-muted);">الكشف المبكر</div>
                        </div>
                    </div>
                </div>
                <?php if ($qi_insights): ?>
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:5px;">
                    <?php foreach ($qi_insights as $in): 
                        $bg = $in['type'] === 'critical' ? '#fef2f2' : ($in['type'] === 'warning' ? '#fffbeb' : '#eff6ff');
                        $border = $in['type'] === 'critical' ? '#dc2626' : ($in['type'] === 'warning' ? '#f59e0b' : '#3b82f6');
                    ?>
                    <div style="padding:6px 10px;background:<?php echo $bg; ?>;border-radius:6px;border-right:3px solid <?php echo $border; ?>;font-size:12px;">
                        <?php echo $in['icon']; ?> <?php echo $in['text']; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Nationality Table -->
            <div class="card">
                <div class="card-header"><div class="card-title">🌍 توزيع الجنسيات</div></div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($nation_dist as $n): ?>
                    <span style="background:var(--teal-pale);padding:4px 12px;border-radius:20px;font-size:13px;">
                        <?php echo escape_output($n['nationality']); ?> <strong><?php echo $n['cnt']; ?></strong>
                    </span>
                    <?php endforeach; ?>
                    <?php if (empty($nation_dist)): ?><p style="color:#94a3b8;">لا توجد بيانات</p><?php endif; ?>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Age chart
    const ageData = <?php echo json_encode($age_dist, JSON_UNESCAPED_UNICODE); ?>;
    if (ageData.length > 0) {
        const ac = new ClinicChart('ageChart');
        ac.drawBarChart(
            ageData.map(d => d.age_group),
            ageData.map(d => parseInt(d.cnt)),
            { colors: ['#0a7e6e', '#13a896', '#c9a84c', '#f59e0b', '#ef4444', '#8b5cf6'] }
        );
    }

    // Gender chart
    const gc = new ClinicChart('genderChart');
    gc.drawPieChart([
        { label: 'ذكر', value: <?php echo $male; ?> },
        { label: 'أنثى', value: <?php echo $female; ?> }
    ], { donut: true });

    // Trends chart
    const visitsData = <?php echo json_encode($monthly_visits, JSON_UNESCAPED_UNICODE); ?>;
    const newData = <?php echo json_encode($monthly_new, JSON_UNESCAPED_UNICODE); ?>;
    const healedData = <?php echo json_encode($monthly_healed, JSON_UNESCAPED_UNICODE); ?>;
    
    if (visitsData.length > 0) {
        // Merge all months
        const allMonths = [...new Set([...visitsData.map(d => d.month), ...newData.map(d => d.month), ...healedData.map(d => d.month)])].sort();
        
        const visitsMap = {}; visitsData.forEach(d => visitsMap[d.month] = parseInt(d.cnt));
        const newMap = {}; newData.forEach(d => newMap[d.month] = parseInt(d.cnt));
        const healedMap = {}; healedData.forEach(d => healedMap[d.month] = parseInt(d.cnt));

        // We'll use overlay approach - draw 3 separate charts or use the ClinicChart to draw lines
        // For simplicity, we'll show as a combined chart using drawLineChart with visit data
        const tc = new ClinicChart('trendsChart');
        const months = allMonths.map(m => m.slice(5,7) + '/' + m.slice(0,4));
        // Just show visits for now - the chart is a simple implementation
        tc.drawLineChart(
            months,
            allMonths.map(m => visitsMap[m] || 0),
            { lineColor: '#3b82f6', fillColor: 'rgba(59, 130, 246, 0.1)' }
        );
    }

    // Wagner chart
    const wagnerData = <?php echo json_encode($wagner_dist, JSON_UNESCAPED_UNICODE); ?>;
    if (wagnerData.length > 0) {
        const wc = new ClinicChart('wagnerChartHub');
        wc.drawBarChart(
            wagnerData.map(d => d.grade == -1 ? 'غير محدد' : 'W' + d.grade),
            wagnerData.map(d => parseInt(d.cnt)),
            { colors: ['#10b981', '#f59e0b', '#f97316', '#ef4444', '#dc2626', '#7f1d1d'] }
        );
    }

    // Outcome chart
    const outcomeData = <?php echo json_encode($outcome_dist, JSON_UNESCAPED_UNICODE); ?>;
    if (outcomeData.some(d => d.cnt > 0)) {
        const oc = new ClinicChart('outcomeChart');
        oc.drawPieChart(
            outcomeData.filter(d => d.cnt > 0).map(d => ({ label: d.status, value: parseInt(d.cnt) })),
            { donut: true, colors: ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444'] }
        );
    }
});
</script>
