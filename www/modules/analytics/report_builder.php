<?php
/**
 * Custom Report Builder - Build your own analytics reports
 */
$page_title = '📊 منشئ التقارير | Report Builder';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));
$selected_metrics = isset($_GET['metrics']) ? (array)$_GET['metrics'] : ['total_patients', 'total_visits', 'healed_count'];
$chart_type = $_GET['chart_type'] ?? 'table';
$group_by = $_GET['group_by'] ?? 'month';
$export = $_GET['export'] ?? '';

// Define available metrics
$all_metrics = [
    'total_patients' => ['label' => 'إجمالي المرضى', 'sql' => "SELECT COUNT(*) as val FROM patients WHERE is_active = 1"],
    'total_visits' => ['label' => 'إجمالي الزيارات', 'sql' => "SELECT COUNT(*) as val FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'"],
    'healed_count' => ['label' => 'تم شفاؤهم', 'sql' => "SELECT COUNT(DISTINCT v.patient_id) as val FROM visits v JOIN outcomes o ON v.visit_id = o.visit_id WHERE o.improvement_percentage >= 100 AND v.visit_date BETWEEN '$from_date' AND '$to_date'"],
    'active_wounds' => ['label' => 'جروح نشطة', 'sql' => "SELECT COUNT(DISTINCT v.patient_id) as val FROM visits v JOIN foot_ulcers fu ON v.visit_id = fu.visit_id LEFT JOIN outcomes o ON v.visit_id = o.visit_id WHERE (o.improvement_percentage IS NULL OR o.improvement_percentage < 100) AND v.visit_date BETWEEN '$from_date' AND '$to_date'"],
    'high_risk' => ['label' => 'حالات خطرة', 'sql' => "SELECT COUNT(DISTINCT v.patient_id) as val FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3 AND v.visit_date BETWEEN '$from_date' AND '$to_date'"],
    'avg_hba1c' => ['label' => 'متوسط HbA1c', 'sql' => "SELECT ROUND(AVG(bs.hba1c_value), 1) as val FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'"],
    'amputations' => ['label' => 'حالات البتر', 'sql' => "SELECT COUNT(*) as val FROM outcomes WHERE current_amputation IS NOT NULL AND current_amputation != 'لا'"],
    'avg_healing_days' => ['label' => 'متوسط أيام الشفاء', 'sql' => "SELECT ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as val FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id WHERE o.healing_date IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'"],
];

// grouped data
$group_results = [];
if ($group_by === 'month') {
    $group_sql = "SELECT DATE_FORMAT(visit_date, '%Y-%m') as label, COUNT(*) as val FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date' GROUP BY label ORDER BY label LIMIT 24";
} elseif ($group_by === 'city') {
    $group_sql = "SELECT COALESCE(city, 'غير محدد') as label, COUNT(*) as val FROM patients WHERE is_active = 1 GROUP BY label ORDER BY val DESC LIMIT 10";
} elseif ($group_by === 'wagner') {
    $group_sql = "SELECT CONCAT('Wagner ', COALESCE(wagner_grade, 'N/A')) as label, COUNT(*) as val FROM foot_assessments GROUP BY wagner_grade ORDER BY wagner_grade";
} elseif ($group_by === 'weekday') {
    $group_sql = "SELECT DAYNAME(visit_date) as label, COUNT(*) as val FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date' GROUP BY DAYNAME(visit_date) ORDER BY DAYOFWEEK(visit_date)";
}

$group_data = [];
if (isset($group_sql)) {
    $result = $mysqli->query($group_sql);
    if ($result) while ($row = $result->fetch_assoc()) { $group_data[] = $row; }
}

// Calculate stat values for header
$stat_values = [];
foreach ($all_metrics as $key => $metric) {
    $result = $mysqli->query($metric['sql']);
    if ($result) $stat_values[$key] = $result->fetch_assoc()['val'];
}

// CSV Export
if ($export === 'csv' && !empty($group_data)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
    fputcsv($output, ['الفئة', 'القيمة']);
    foreach ($group_data as $row) fputcsv($output, [$row['label'], $row['val']]);
    fclose($output);
    exit;
}
?>
<style>
    .rp-card { padding: 1rem; border-radius: 12px; border: 1px solid var(--border); }
    .rp-card:hover { box-shadow: var(--shadow-lg); }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header flex justify-between items-center flex-wrap gap-3">
    <div><h1 class="page-title">📊 منشئ التقارير</h1><p class="page-subtitle">Report Builder — أنشئ تقارير مخصصة</p></div>
    <?php if (!empty($group_data)): ?>
    <a href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&group_by=<?php echo $group_by; ?>&chart_type=<?php echo $chart_type; ?>&export=csv" class="btn btn-sm btn-primary">📥 CSV</a>
    <?php endif; ?>
</div>

<!-- Builder Form -->
<div class="card mb-4">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="field" style="min-width:150px;">
            <label>من تاريخ</label>
            <input type="date" name="from_date" value="<?php echo $from_date; ?>">
        </div>
        <div class="field" style="min-width:150px;">
            <label>إلى تاريخ</label>
            <input type="date" name="to_date" value="<?php echo $to_date; ?>">
        </div>
        <div class="field" style="min-width:150px;">
            <label>التجميع</label>
            <select name="group_by">
                <option value="month" <?php echo $group_by === 'month' ? 'selected' : ''; ?>>شهري</option>
                <option value="city" <?php echo $group_by === 'city' ? 'selected' : ''; ?>>مدينة</option>
                <option value="wagner" <?php echo $group_by === 'wagner' ? 'selected' : ''; ?>>Wagner</option>
                <option value="weekday" <?php echo $group_by === 'weekday' ? 'selected' : ''; ?>>أسبوعي</option>
            </select>
        </div>
        <div class="field" style="min-width:120px;">
            <label>نوع الرسم</label>
            <select name="chart_type">
                <option value="table" <?php echo $chart_type === 'table' ? 'selected' : ''; ?>>جدول</option>
                <option value="bar" <?php echo $chart_type === 'bar' ? 'selected' : ''; ?>>أعمدة</option>
                <option value="pie" <?php echo $chart_type === 'pie' ? 'selected' : ''; ?>>دائري</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">🔄 تحديث</button>
    </form>
</div>

<!-- Summary Stats -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));">
    <?php foreach ($all_metrics as $key => $metric): ?>
    <div class="rp-card" style="text-align:center;">
        <div style="font-size:1.3rem;font-weight:800;color:var(--teal);"><?php echo $stat_values[$key] ?? '—'; ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo $metric['label']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Chart / Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">📈 <?php echo $group_by === 'month' ? 'اتجاه شهري' : ($group_by === 'city' ? 'توزيع مدن' : ($group_by === 'wagner' ? 'توزيع Wagner' : 'توزيع أيام')); ?></div>
    </div>
    <?php if (empty($group_data)): ?>
    <p class="text-muted text-center" style="padding:2rem;">لا توجد بيانات</p>
    <?php elseif ($chart_type === 'table'): ?>
    <div class="table-container">
        <table><thead><tr><th>الفئة</th><th>القيمة</th></tr></thead>
        <tbody><?php foreach ($group_data as $r): ?><tr><td><?php echo escape_output($r['label']); ?></td><td><strong><?php echo $r['val']; ?></strong></td></tr><?php endforeach; ?></tbody></table>
    </div>
    <?php else: ?>
    <div style="height:300px;"><canvas id="reportChart" width="800" height="300"></canvas></div>
    <?php endif; ?>
</div>

</div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($chart_type !== 'table' && !empty($group_data)): ?>
    const labels = <?php echo json_encode(array_column($group_data, 'label'), JSON_UNESCAPED_UNICODE); ?>;
    const values = <?php echo json_encode(array_map('intval', array_column($group_data, 'val'))); ?>;
    const chart = new ClinicChart('reportChart');
    <?php if ($chart_type === 'bar'): ?>
    chart.drawBarChart(labels, values, { colors: ['#0a7e6e','#3b82f6','#f59e0b','#8b5cf6','#10b981','#ec4899','#6366f1'] });
    <?php else: ?>
    chart.drawPieChart(labels.map((l,i) => ({ label: l, value: values[i] })), { donut: true });
    <?php endif; ?>
    <?php endif; ?>
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
