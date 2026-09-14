<?php
/**
 * Advanced Diabetic Trend Analysis
 */
$page_title = '🩸 تحليلات السكري المتقدمة | Advanced Diabetic Trends';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-24 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// 1. HbA1c Trajectory Patterns
$hba1c_trajectory = [];
$result = $mysqli->query("SELECT 
    COUNT(DISTINCT CASE WHEN bs2.hba1c_value < bs1.hba1c_value THEN v.patient_id END) as improved,
    COUNT(DISTINCT CASE WHEN ABS(bs2.hba1c_value - bs1.hba1c_value) <= 0.3 THEN v.patient_id END) as stable,
    COUNT(DISTINCT CASE WHEN bs2.hba1c_value > bs1.hba1c_value THEN v.patient_id END) as worsened
FROM blood_sugar_readings bs1
JOIN visits v ON bs1.visit_id = v.visit_id
JOIN blood_sugar_readings bs2 ON v.patient_id = (SELECT patient_id FROM visits WHERE visit_id = bs2.visit_id) 
    AND bs2.reading_id > bs1.reading_id
WHERE bs1.hba1c_value IS NOT NULL AND bs2.hba1c_value IS NOT NULL
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'");
if ($result) $hba1c_trajectory = $result->fetch_assoc();

// 2. Seasonal HbA1c Patterns
$seasonal = [];
$result = $mysqli->query("SELECT 
    MONTH(v.visit_date) as month_num,
    CASE 
        WHEN MONTH(v.visit_date) BETWEEN 3 AND 5 THEN 'الربيع'
        WHEN MONTH(v.visit_date) BETWEEN 6 AND 8 THEN 'الصيف'
        WHEN MONTH(v.visit_date) BETWEEN 9 AND 11 THEN 'الخريف'
        ELSE 'الشتاء'
    END as season,
    AVG(bs.hba1c_value) as avg_hba1c,
    COUNT(*) as readings
FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
WHERE bs.hba1c_value IS NOT NULL AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
GROUP BY season, MONTH(v.visit_date)
ORDER BY month_num");
while ($row = $result->fetch_assoc()) { $seasonal[] = $row; }

// 3. Treatment Response Curves
$treatment_response = [];
$result = $mysqli->query("SELECT 
    t.treatment_type,
    ROUND(AVG(bs.hba1c_value), 1) as avg_hba1c,
    COUNT(*) as total
FROM treatments t
JOIN visits v ON t.visit_id = v.visit_id
JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
WHERE bs.hba1c_value IS NOT NULL AND t.treatment_type IS NOT NULL AND t.treatment_type != ''
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
GROUP BY t.treatment_type
ORDER BY avg_hba1c DESC");
while ($row = $result->fetch_assoc()) { $treatment_response[] = $row; }

// 4. Complication Progression
$complication_progression = [];
$result = $mysqli->query("SELECT 
    mh.duration_years,
    ROUND(AVG(c.has_retinopathy + c.has_nephropathy + c.has_neuropathy + c.has_cad + c.has_cva + c.has_pad), 2) as avg_complications,
    COUNT(*) as total
FROM medical_history mh
JOIN complications c ON mh.patient_id = c.patient_id
WHERE mh.duration_years IS NOT NULL
GROUP BY mh.duration_years
ORDER BY mh.duration_years ASC");
while ($row = $result->fetch_assoc()) { $complication_progression[] = $row; }

// 5. FPG vs HbA1c correlation data
$fpg_hba1c = [];
$result = $mysqli->query("SELECT bs.fpg_value, bs.hba1c_value 
    FROM blood_sugar_readings bs WHERE bs.fpg_value IS NOT NULL AND bs.hba1c_value IS NOT NULL 
    LIMIT 200");
while ($row = $result->fetch_assoc()) { $fpg_hba1c[] = $row; }

// Correlation ratio
$fpg_hba1c_ratio = 0;
$fpg_hba1c_count = 0;
foreach ($fpg_hba1c as $r) {
    if ($r['fpg_value'] && $r['hba1c_value']) {
        $fpg_hba1c_ratio += $r['fpg_value'] / $r['hba1c_value'];
        $fpg_hba1c_count++;
    }
}
$avg_fpg_ratio = $fpg_hba1c_count > 0 ? round($fpg_hba1c_ratio / $fpg_hba1c_count, 1) : 0;

// 6. Monthly HbA1c average trend
$monthly_hba1c = [];
$result = $mysqli->query("SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as month, 
    ROUND(AVG(bs.hba1c_value), 1) as avg_hba1c,
    ROUND(AVG(bs.fpg_value), 1) as avg_fpg,
    COUNT(*) as readings
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $monthly_hba1c[] = $row; }
?>
<style>
    .trend-stat { text-align: center; padding: 1rem; border-radius: 12px; border: 1px solid var(--border); }
    .trend-stat .value { font-size: 1.8rem; font-weight: 800; }
    .trend-stat .label { font-size: 0.8rem; color: var(--text-muted); }
    .pattern-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header"><h1 class="page-title">🩸 تحليلات السكري المتقدمة</h1><p class="page-subtitle">Advanced Diabetic Trends — أنماط HbA1c، اتجاهات موسمية، استجابة العلاج</p></div>

<!-- Trajectory Summary -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <div class="trend-stat"><div class="value" style="color:var(--green);"><?php echo (int)($hba1c_trajectory['improved'] ?? 0); ?></div><div class="label">✅ تحسن HbA1c</div></div>
    <div class="trend-stat"><div class="value" style="color:var(--text-muted);"><?php echo (int)($hba1c_trajectory['stable'] ?? 0); ?></div><div class="label">➖ مستقر</div></div>
    <div class="trend-stat"><div class="value" style="color:var(--red);"><?php echo (int)($hba1c_trajectory['worsened'] ?? 0); ?></div><div class="label">🔴 تدهور HbA1c</div></div>
    <div class="trend-stat"><div class="value" style="color:var(--teal);"><?php echo count($seasonal); ?></div><div class="label">مواسم</div></div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Monthly HbA1c Trend -->
    <div class="card" style="flex:2;min-width:350px;">
        <div class="card-header"><div class="card-title">📈 اتجاه HbA1c الشهري</div></div>
        <div style="height:220px;"><canvas id="hba1cMonthlyChart" width="600" height="220"></canvas></div>
    </div>
    <!-- Seasonal Pattern -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">🌤️ الأنماط الموسمية لـ HbA1c</div></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php 
            $season_avg = [];
            foreach ($seasonal as $s) {
                $season_avg[$s['season']]['sum'] = ($season_avg[$s['season']]['sum'] ?? 0) + $s['avg_hba1c'];
                $season_avg[$s['season']]['count'] = ($season_avg[$s['season']]['count'] ?? 0) + 1;
            }
            foreach (['الشتاء', 'الربيع', 'الصيف', 'الخريف'] as $season):
                if (isset($season_avg[$season])):
                    $avg = round($season_avg[$season]['sum'] / $season_avg[$season]['count'], 1);
            ?>
            <div style="display:flex;justify-content:space-between;padding:8px;background:var(--bg-input);border-radius:8px;">
                <span style="font-weight:600;"><?php echo $season; ?></span>
                <span class="badge badge-<?php echo $avg > 7 ? 'danger' : 'success'; ?>"><?php echo $avg; ?>%</span>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Treatment Response -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">💊 استجابة العلاج (متوسط HbA1c)</div></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($treatment_response as $tr): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:6px;background:var(--bg-input);border-radius:8px;">
                <span style="flex:1;font-size:13px;font-weight:600;"><?php echo escape_output($tr['treatment_type']); ?></span>
                <span class="badge badge-<?php echo $tr['avg_hba1c'] > 7 ? 'danger' : ($tr['avg_hba1c'] > 6.5 ? 'warning' : 'success'); ?>"><?php echo $tr['avg_hba1c']; ?>%</span>
                <span style="font-size:11px;color:var(--text-muted);">(<?php echo $tr['total']; ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Complication Progression -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">⏱ تطور المضاعفات (حسب السنوات)</div></div>
        <div style="height:200px;"><canvas id="compProgressionChart" width="400" height="200"></canvas></div>
    </div>
    <!-- FPG Correlation -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">📊 ارتباط FPG بـ HbA1c</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;font-size:13px;">
            <?php if ($fpg_hba1c): ?>
            <div class="trend-stat"><div class="value" style="font-size:1.3rem;color:var(--teal);"><?php echo $avg_fpg_ratio; ?></div><div class="label">متوسط FPG/HbA1c</div></div>
            <div style="font-size:11px;color:var(--text-muted);">بناءً على <?php echo $fpg_hba1c_count; ?> قراءة</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthly = <?php echo json_encode($monthly_hba1c, JSON_UNESCAPED_UNICODE); ?>;
    if (monthly.length > 0) {
        const chart = new ClinicChart('hba1cMonthlyChart');
        chart.drawLineChart(
            monthly.map(d => d.month.slice(5)),
            monthly.map(d => parseFloat(d.avg_hba1c)),
            { lineColor: '#dc2626', fillColor: 'rgba(220,38,38,0.08)', lineWidth: 3 }
        );
    }
    const compData = <?php echo json_encode($complication_progression, JSON_UNESCAPED_UNICODE); ?>;
    if (compData.length > 0) {
        const chart = new ClinicChart('compProgressionChart');
        chart.drawLineChart(
            compData.map(d => d.duration_years + 'س'),
            compData.map(d => parseFloat(d.avg_complications)),
            { lineColor: '#8b5cf6', fillColor: 'rgba(139,92,246,0.08)' }
        );
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
