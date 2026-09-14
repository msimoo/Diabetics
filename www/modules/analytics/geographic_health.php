<?php
/**
 * Geographic Health Map - Analyze outcomes by city and distance
 */
$page_title = '🏙️ الخريطة الصحية | Geographic Health';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Patients by city with key metrics
$city_data = [];
$result = $mysqli->query("
    SELECT 
        COALESCE(p.city, 'غير محدد') as city,
        COUNT(DISTINCT p.patient_id) as total_patients,
        ROUND(AVG(p.distance_from_center), 1) as avg_distance,
        COUNT(DISTINCT CASE WHEN fa.wagner_grade >= 3 THEN p.patient_id END) as high_wagner,
        COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN p.patient_id END) as healed,
        ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
        ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days
    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.is_active = 1 AND p.city IS NOT NULL AND p.city != ''
    GROUP BY p.city
    ORDER BY total_patients DESC
    LIMIT 10
");
while ($row = $result->fetch_assoc()) { $city_data[] = $row; }

// Distance vs outcome correlation
$distance_corr = [];
$result = $mysqli->query("
    SELECT 
        CASE 
            WHEN p.distance_from_center <= 5 THEN '0-5 كم'
            WHEN p.distance_from_center <= 15 THEN '6-15 كم'
            WHEN p.distance_from_center <= 30 THEN '16-30 كم'
            WHEN p.distance_from_center <= 50 THEN '31-50 كم'
            ELSE '50+ كم'
        END as distance_group,
        COUNT(DISTINCT p.patient_id) as total,
        ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
        ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days,
        COUNT(DISTINCT CASE WHEN fa.wagner_grade >= 3 THEN p.patient_id END) as high_wagner,
        COUNT(DISTINCT v.visit_id) as total_visits,
        ROUND(COUNT(DISTINCT v.visit_id) / NULLIF(COUNT(DISTINCT p.patient_id), 0), 1) as visits_per_patient
    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.is_active = 1 AND p.distance_from_center IS NOT NULL
    GROUP BY distance_group
    ORDER BY MIN(p.distance_from_center)
");
while ($row = $result->fetch_assoc()) { $distance_corr[] = $row; }

// Top cities by patient count
$top_cities = array_slice($city_data, 0, 5);
?>
<style>
    .geo-card { padding: 1rem; border-radius: 12px; border: 1px solid var(--border); transition: var(--transition); }
    .geo-card:hover { box-shadow: var(--shadow-lg); }
    .city-bar { height: 24px; border-radius: 12px; overflow: hidden; background: var(--border); }
    .city-bar-fill { height: 100%; border-radius: 12px; transition: width 0.8s ease; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; font-size: 11px; font-weight: 700; color: #fff; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header"><h1 class="page-title">🏙️ الخريطة الصحية الجغرافية</h1><p class="page-subtitle">Geographic Health Map — تحليل النتائج حسب المدينة والمسافة</p></div>

<!-- Top Cities -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">🏆 المدن الأكثر تمثيلاً</div></div>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php $max_city = $top_cities ? max(array_column($top_cities, 'total_patients')) : 1; ?>
        <?php foreach ($top_cities as $c): 
            $pct = round($c['total_patients'] / $max_city * 100);
            $heal_rate = $c['total_patients'] > 0 ? round($c['healed'] / $c['total_patients'] * 100, 1) : 0;
        ?>
        <div>
            <div class="flex justify-between" style="font-size:14px;margin-bottom:4px;">
                <span style="font-weight:700;"><?php echo escape_output($c['city']); ?></span>
                <span><?php echo $c['total_patients']; ?> مريض | 🏥 <?php echo $c['avg_distance'] ? $c['avg_distance'] . ' كم' : '—'; ?></span>
            </div>
            <div class="city-bar"><div class="city-bar-fill" style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,var(--teal),var(--teal-light));"><?php echo $c['total_patients']; ?></div></div>
            <div class="flex gap-3 mt-1" style="font-size:11px;color:var(--text-muted);">
                <span>✅ شفاء: <?php echo $heal_rate; ?>%</span>
                <span>📈 تحسن: <?php echo $c['avg_improvement'] ?: '—'; ?>%</span>
                <span>⚠️ Wagner 3+: <?php echo $c['high_wagner']; ?></span>
                <span>⏱ <?php echo $c['avg_healing_days'] ? $c['avg_healing_days'] . ' يوم' : '—'; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Distance Correlation -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">📏 تأثير المسافة على النتائج</div></div>
    <div class="table-container">
        <table>
            <thead><tr><th>مجموعة المسافة</th><th>المرضى</th><th>الزيارات</th><th>زيارة/مريض</th><th>Wagner 3+</th><th>متوسط التحسن</th><th>أيام الشفاء</th></tr></thead>
            <tbody>
                <?php foreach ($distance_corr as $d): ?>
                <tr>
                    <td><strong><?php echo $d['distance_group']; ?></strong></td>
                    <td><?php echo $d['total']; ?></td>
                    <td><?php echo $d['total_visits']; ?></td>
                    <td><?php echo $d['visits_per_patient']; ?></td>
                    <td><span class="badge badge-danger"><?php echo $d['high_wagner']; ?></span></td>
                    <td><?php echo $d['avg_improvement'] ? $d['avg_improvement'] . '%' : '—'; ?></td>
                    <td><?php echo $d['avg_healing_days'] ? $d['avg_healing_days'] . ' يوم' : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- All Cities Table -->
<div class="card">
    <div class="card-header"><div class="card-title">📊 جميع المدن</div></div>
    <div class="table-container">
        <table>
            <thead><tr><th>المدينة</th><th>المرضى</th><th>المسافة</th><th>Wagner 3+</th><th>الشفاء</th><th>التحسن</th><th>أيام الشفاء</th></tr></thead>
            <tbody>
                <?php foreach ($city_data as $c): 
                    $heal_rate = $c['total_patients'] > 0 ? round($c['healed'] / $c['total_patients'] * 100, 1) : 0;
                ?>
                <tr>
                    <td style="font-weight:600;"><?php echo escape_output($c['city']); ?></td>
                    <td><?php echo $c['total_patients']; ?></td>
                    <td><?php echo $c['avg_distance'] ? $c['avg_distance'] . ' كم' : '—'; ?></td>
                    <td><span class="badge badge-<?php echo $c['high_wagner'] > 0 ? 'danger' : 'success'; ?>"><?php echo $c['high_wagner']; ?></span></td>
                    <td><strong style="color:var(--green);"><?php echo $heal_rate; ?>%</strong></td>
                    <td><?php echo $c['avg_improvement'] ?: '—'; ?>%</td>
                    <td><?php echo $c['avg_healing_days'] ? $c['avg_healing_days'] . ' يوم' : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
