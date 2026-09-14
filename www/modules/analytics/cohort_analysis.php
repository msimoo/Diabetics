<?php
/**
 * Cohort Analysis - Compare patient groups over time
 */
$page_title = '👥 تحليل المجموعات | Cohort Analysis';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-36 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// === COHORT DEFINITIONS ===

// 1. Diagnosis Year Cohorts
$diag_cohorts = [];
$result = $mysqli->query("
    SELECT diagnosis_year as cohort, COUNT(DISTINCT p.patient_id) as total,
           ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
           ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days,
           COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN p.patient_id END) as healed
    FROM patients p
    JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE mh.diagnosis_year IS NOT NULL
    GROUP BY mh.diagnosis_year
    ORDER BY mh.diagnosis_year DESC
    LIMIT 10
");
while ($row = $result->fetch_assoc()) { $diag_cohorts[] = $row; }

// 2. Treatment Type Cohorts
$treatment_cohorts = [];
$result = $mysqli->query("
    SELECT t.treatment_type as cohort, COUNT(DISTINCT p.patient_id) as total,
           ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
           ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days,
           COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN p.patient_id END) as healed
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    JOIN treatments t ON v.visit_id = t.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE t.treatment_type IS NOT NULL AND t.treatment_type != ''
    GROUP BY t.treatment_type
    ORDER BY total DESC
");
while ($row = $result->fetch_assoc()) { $treatment_cohorts[] = $row; }

// 3. Wagner Grade Cohorts  
$wagner_cohorts = [];
$result = $mysqli->query("
    SELECT CONCAT('Wagner ', fa.wagner_grade) as cohort, COUNT(DISTINCT p.patient_id) as total,
           ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
           ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_healing_days,
           COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN p.patient_id END) as healed
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fa.wagner_grade IS NOT NULL
    GROUP BY fa.wagner_grade
    ORDER BY fa.wagner_grade ASC
");
while ($row = $result->fetch_assoc()) { $wagner_cohorts[] = $row; }

// 4. Age Group Cohorts
$age_cohorts = [];
$result = $mysqli->query("
    SELECT CASE 
        WHEN p.age < 30 THEN '<30'
        WHEN p.age BETWEEN 30 AND 45 THEN '30-45'
        WHEN p.age BETWEEN 46 AND 60 THEN '46-60'
        ELSE '60+'
    END as cohort, COUNT(DISTINCT p.patient_id) as total,
    ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
    COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN p.patient_id END) as healed
    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.age IS NOT NULL
    GROUP BY cohort
    ORDER BY cohort
");
while ($row = $result->fetch_assoc()) { $age_cohorts[] = $row; }

// 5. Healing Curve Data (by month since first visit)
$healing_curve = [];
$result = $mysqli->query("
    SELECT 
        TIMESTAMPDIFF(MONTH, p.first_visit_date, v.visit_date) as month_since_first,
        COUNT(DISTINCT v.patient_id) as active_patients,
        COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) as healed
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.first_visit_date IS NOT NULL AND p.first_visit_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
    GROUP BY month_since_first
    HAVING month_since_first BETWEEN 0 AND 24
    ORDER BY month_since_first
");
while ($row = $result->fetch_assoc()) { $healing_curve[] = $row; }

// Calculate cumulative healing rate
$cumulative = [];
$total_patients = 0;
$cumulative_healed = 0;
foreach ($healing_curve as $row) {
    $total_patients = max($total_patients, (int)$row['active_patients']);
}
$running_healed = 0;
foreach ($healing_curve as $row) {
    $running_healed = max($running_healed, (int)$row['healed']);
    $cumulative[] = [
        'month' => $row['month_since_first'],
        'rate' => $total_patients > 0 ? round($running_healed / $total_patients * 100, 1) : 0
    ];
}
?>
<style>
    .cohort-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; }
    .cohort-card:hover { box-shadow: var(--shadow-lg); }
    .cohort-bar { height: 20px; border-radius: 10px; overflow: hidden; background: var(--border); }
    .cohort-bar-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease; }
    .healing-curve-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--teal); position: absolute; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header"><h1 class="page-title">👥 تحليل المجموعات</h1><p class="page-subtitle">Cohort Analysis — مقارنة مجموعات المرضى والنتائج</p></div>

<!-- Diagnosis Year Cohorts -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">📅 مجموعات سنة التشخيص</div></div>
    <?php if ($diag_cohorts): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
        <?php foreach ($diag_cohorts as $c): 
            $heal_rate = $c['total'] > 0 ? round($c['healed'] / $c['total'] * 100, 1) : 0;
        ?>
        <div class="cohort-card">
            <div style="font-weight:800;font-size:1.2rem;color:var(--teal);"><?php echo $c['cohort']; ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted);"><?php echo $c['total']; ?> مريض</div>
            <div class="flex justify-between mt-2" style="font-size:0.85rem;">
                <span>الشفاء: <strong><?php echo $heal_rate; ?>%</strong></span>
                <span>تحسن: <strong><?php echo $c['avg_improvement'] ?: '—'; ?>%</strong></span>
            </div>
            <div class="cohort-bar mt-1"><div class="cohort-bar-fill" style="width:<?php echo $heal_rate; ?>%;background:linear-gradient(90deg,var(--teal),var(--teal-light));"></div></div>
            <?php if ($c['avg_healing_days']): ?><div style="font-size:0.75rem;color:var(--text-light);margin-top:4px;">⏱ متوسط <?php echo $c['avg_healing_days']; ?> يوم</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?><p class="text-muted">لا توجد بيانات كافية</p><?php endif; ?>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Treatment Cohorts -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">💊 مجموعات العلاج</div></div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($treatment_cohorts as $c): 
                $heal_rate = $c['total'] > 0 ? round($c['healed'] / $c['total'] * 100, 1) : 0;
            ?>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;">
                <div class="flex justify-between" style="font-size:0.85rem;">
                    <span style="font-weight:600;"><?php echo escape_output($c['cohort']); ?></span>
                    <span><?php echo $c['total']; ?> مريض</span>
                </div>
                <div class="flex gap-3 mt-1" style="font-size:0.8rem;color:var(--text-muted);">
                    <span>✅ <?php echo $heal_rate; ?>% شفاء</span>
                    <span>📈 <?php echo $c['avg_improvement'] ?: '—'; ?>% تحسن</span>
                    <?php if ($c['avg_healing_days']): ?><span>⏱ <?php echo $c['avg_healing_days']; ?> يوم</span><?php endif; ?>
                </div>
                <div class="cohort-bar mt-1"><div class="cohort-bar-fill" style="width:<?php echo $heal_rate; ?>%;background:linear-gradient(90deg,#3b82f6,#60a5fa);"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Wagner Cohorts -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">🦶 مجموعات Wagner</div></div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($wagner_cohorts as $c): 
                $heal_rate = $c['total'] > 0 ? round($c['healed'] / $c['total'] * 100, 1) : 0;
                $color = $c['cohort'] >= 'Wagner 3' ? '#ef4444' : ($c['cohort'] >= 'Wagner 2' ? '#f59e0b' : '#10b981');
            ?>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;border-right:3px solid <?php echo $color; ?>;">
                <div class="flex justify-between" style="font-size:0.85rem;">
                    <span style="font-weight:600;"><?php echo $c['cohort']; ?></span>
                    <span><?php echo $c['total']; ?> مريض</span>
                </div>
                <div class="flex gap-3 mt-1" style="font-size:0.8rem;color:var(--text-muted);">
                    <span>✅ <?php echo $heal_rate; ?>% شفاء</span>
                    <span>📈 <?php echo $c['avg_improvement'] ?: '—'; ?>%</span>
                    <?php if ($c['avg_healing_days']): ?><span>⏱ <?php echo $c['avg_healing_days']; ?> يوم</span><?php endif; ?>
                </div>
                <div class="cohort-bar mt-1"><div class="cohort-bar-fill" style="width:<?php echo $heal_rate; ?>%;background:<?php echo $color; ?>;"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Age Cohorts -->
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">👤 مجموعات العمر</div></div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($age_cohorts as $c): 
                $heal_rate = $c['total'] > 0 ? round($c['healed'] / $c['total'] * 100, 1) : 0;
            ?>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;">
                <div class="flex justify-between" style="font-size:0.85rem;">
                    <span style="font-weight:600;"><?php echo $c['cohort']; ?></span>
                    <span><?php echo $c['total']; ?> مريض</span>
                </div>
                <div style="font-size:0.8rem;color:var(--text-muted);">✅ <?php echo $heal_rate; ?>% شفاء | 📈 <?php echo $c['avg_improvement'] ?: '—'; ?>%</div>
                <div class="cohort-bar mt-1"><div class="cohort-bar-fill" style="width:<?php echo $heal_rate; ?>%;background:linear-gradient(90deg,#8b5cf6,#a78bfa);"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Healing Curve -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">📈 منحنى الشفاء التراكمي (24 شهراً)</div></div>
    <div style="height:260px;"><canvas id="healingCurveChart" width="900" height="260"></canvas></div>
</div>

</div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const curveData = <?php echo json_encode($cumulative, JSON_UNESCAPED_UNICODE); ?>;
    if (curveData.length > 0) {
        const chart = new ClinicChart('healingCurveChart');
        chart.drawLineChart(
            curveData.map(d => 'شهر ' + d.month),
            curveData.map(d => parseFloat(d.rate)),
            { lineColor: '#0a7e6e', fillColor: 'rgba(10,126,110,0.1)', lineWidth: 3 }
        );
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
