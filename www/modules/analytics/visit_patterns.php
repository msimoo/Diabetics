<?php
/**
 * Visit Pattern Analysis - Frequency, dropout, adherence
 */
$page_title = '📅 أنماط الزيارات | Visit Patterns';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// 1. Visit frequency distribution
$frequency = [];
$result = $mysqli->query("
    SELECT 
        v_count.visit_count,
        COUNT(*) as patient_count
    FROM (
        SELECT p.patient_id, COUNT(v.visit_id) as visit_count
        FROM patients p
        JOIN visits v ON p.patient_id = v.patient_id
        WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND p.is_active = 1
        GROUP BY p.patient_id
    ) v_count
    GROUP BY v_count.visit_count
    ORDER BY v_count.visit_count ASC
");
while ($row = $result->fetch_assoc()) { $frequency[] = $row; }

// 2. Dropout analysis (patients who never returned after first visit)
$dropout = $mysqli->query("
    SELECT 
        COUNT(DISTINCT p.patient_id) as total_new,
        COUNT(DISTINCT CASE WHEN (
            SELECT COUNT(*) FROM visits v2 WHERE v2.patient_id = p.patient_id
        ) = 1 THEN p.patient_id END) as dropped_after_first,
        ROUND(COUNT(DISTINCT CASE WHEN (
            SELECT COUNT(*) FROM visits v2 WHERE v2.patient_id = p.patient_id
        ) = 1 THEN p.patient_id END) / NULLIF(COUNT(DISTINCT p.patient_id), 0) * 100, 1) as dropout_rate
    FROM patients p
    WHERE p.created_at BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// 3. Days between visits (return interval)
$return_interval = [];
$result = $mysqli->query("
    SELECT 
        CASE 
            WHEN days_diff <= 14 THEN '1-14 يوم'
            WHEN days_diff <= 30 THEN '15-30 يوم'
            WHEN days_diff <= 60 THEN '31-60 يوم'
            WHEN days_diff <= 90 THEN '61-90 يوم'
            ELSE '90+ يوم'
        END as interval_group,
        COUNT(*) as cnt
    FROM (
        SELECT v.patient_id, DATEDIFF(v.visit_date, 
            (SELECT MAX(v2.visit_date) FROM visits v2 WHERE v2.patient_id = v.patient_id AND v2.visit_date < v.visit_date)
        ) as days_diff
        FROM visits v
        WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
        HAVING days_diff IS NOT NULL
    ) intervals
    GROUP BY interval_group
    ORDER BY MIN(days_diff)
");
while ($row = $result->fetch_assoc()) { $return_interval[] = $row; }

// 4. Adherence tracking (patients with visits in recommended timeframe)
$adherent = $mysqli->query("
    SELECT 
        COUNT(DISTINCT p.patient_id) as total_active,
        COUNT(DISTINCT CASE WHEN (
            SELECT MAX(v.visit_date) FROM visits v WHERE v.patient_id = p.patient_id
        ) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN p.patient_id END) as adherent,
        ROUND(COUNT(DISTINCT CASE WHEN (
            SELECT MAX(v.visit_date) FROM visits v WHERE v.patient_id = p.patient_id
        ) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN p.patient_id END) / NULLIF(COUNT(DISTINCT p.patient_id), 0) * 100, 1) as adherence_rate
    FROM patients p WHERE p.is_active = 1
")->fetch_assoc();

// 5. Top 10 most frequent visitors
$frequent_visitors = $mysqli->query("
    SELECT p.patient_id, p.full_name, p.file_number, COUNT(v.visit_id) as visit_count,
           MIN(v.visit_date) as first_visit, MAX(v.visit_date) as last_visit,
           DATEDIFF(MAX(v.visit_date), MIN(v.visit_date)) as span_days,
           ROUND(COUNT(v.visit_id) / NULLIF(DATEDIFF(MAX(v.visit_date), MIN(v.visit_date)), 0) * 30, 1) as visits_per_month
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    WHERE p.is_active = 1
    GROUP BY p.patient_id
    HAVING visit_count >= 5
    ORDER BY visit_count DESC
    LIMIT 10
");
?>
<style>
    .vp-card { padding: 1rem; border-radius: 12px; border: 1px solid var(--border); text-align: center; transition: var(--transition); }
    .vp-card:hover { box-shadow: var(--shadow-lg); }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header"><h1 class="page-title">📅 أنماط الزيارات</h1><p class="page-subtitle">Visit Pattern Analysis — تكرار، تسرب، التزام</p></div>

<!-- Summary Stats -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <div class="vp-card"><div class="stat-value" style="color:var(--teal);font-size:1.3rem;"><?php echo $dropout['total_new'] ?? 0; ?></div><div>مرضى جدد</div></div>
    <div class="vp-card"><div class="stat-value" style="color:var(--red);font-size:1.3rem;"><?php echo $dropout['dropped_after_first'] ?? 0; ?></div><div>لم يعودوا بعد الأولى</div></div>
    <div class="vp-card"><div class="stat-value" style="color:var(--orange);font-size:1.3rem;"><?php echo $dropout['dropout_rate'] ?? 0; ?>%</div><div>نسبة التسرب</div></div>
    <div class="vp-card"><div class="stat-value" style="color:var(--green);font-size:1.3rem;"><?php echo $adherent['adherence_rate'] ?? 0; ?>%</div><div>نسبة الالتزام</div></div>
    <div class="vp-card"><div class="stat-value" style="color:var(--blue);font-size:1.3rem;"><?php echo $adherent['adherent'] ?? 0; ?></div><div>ملتزم (&lt;60 يوم)</div></div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Visit Frequency -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">📊 توزيع تكرار الزيارات</div></div>
        <div style="height:220px;"><canvas id="frequencyChart" width="400" height="220"></canvas></div>
    </div>
    <!-- Return Interval -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">⏱ الفاصل بين الزيارات</div></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($return_interval as $ri): ?>
            <div style="display:flex;justify-content:space-between;padding:6px 10px;background:var(--bg-input);border-radius:8px;font-size:13px;">
                <span><?php echo $ri['interval_group']; ?></span>
                <span class="badge badge-info"><?php echo $ri['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Top Frequent Visitors -->
<div class="card">
    <div class="card-header"><div class="card-title">🏆 الأكثر تردداً على العيادة</div></div>
    <div class="table-container">
        <table>
            <thead><tr><th>المريض</th><th>الزيارات</th><th>أول زيارة</th><th>آخر زيارة</th><th>المدة</th><th>زيارة/شهر</th></tr></thead>
            <tbody>
                <?php while ($fv = $frequent_visitors->fetch_assoc()): ?>
                <tr>
                    <td><a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $fv['patient_id']; ?>" style="font-weight:600;"><?php echo escape_output($fv['full_name']); ?></a></td>
                    <td><strong style="color:var(--teal);"><?php echo $fv['visit_count']; ?></strong></td>
                    <td style="font-size:12px;"><?php echo $fv['first_visit']; ?></td>
                    <td style="font-size:12px;"><?php echo $fv['last_visit']; ?></td>
                    <td style="font-size:12px;"><?php echo $fv['span_days']; ?> يوم</td>
                    <td><?php echo $fv['visits_per_month']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const freqData = <?php echo json_encode($frequency, JSON_UNESCAPED_UNICODE); ?>;
    if (freqData.length > 0) {
        new ClinicChart('frequencyChart').drawBarChart(
            freqData.map(d => d.visit_count + ' ز'),
            freqData.map(d => parseInt(d.patient_count)),
            { colors: ['#0a7e6e', '#3b82f6', '#f59e0b', '#8b5cf6', '#10b981', '#ec4899', '#6366f1'] }
        );
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
