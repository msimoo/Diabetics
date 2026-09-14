<?php
/**
 * Clinic Flow Analytics - Wait times, visit duration, no-shows, capacity
 */
$page_title = '⏱ تدفق العيادة | Clinic Flow';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-3 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// Ensure appointments table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    user_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    appointment_type ENUM('متابعة', 'فحص قدم', 'استشارة', 'إجراء', 'تحليل', 'أخرى') NOT NULL DEFAULT 'متابعة',
    status ENUM('مؤكد', 'قيد الانتظار', 'ملغي', 'مكتمل', 'لم يحضر') NOT NULL DEFAULT 'قيد الانتظار',
    notes TEXT,
    reminder_sent TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 1. No-show rate
$no_show = $mysqli->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'لم يحضر' THEN 1 ELSE 0 END) as no_shows,
    ROUND(SUM(CASE WHEN status = 'لم يحضر' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 1) as rate
FROM appointments WHERE appointment_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// 2. No-show by day of week
$no_show_by_day = [];
$result = $mysqli->query("SELECT 
    DAYNAME(appointment_date) as day_name,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'لم يحضر' THEN 1 ELSE 0 END) as no_shows,
    ROUND(SUM(CASE WHEN status = 'لم يحضر' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 1) as rate
FROM appointments WHERE appointment_date BETWEEN '$from_date' AND '$to_date'
GROUP BY DAYNAME(appointment_date) ORDER BY DAYOFWEEK(appointment_date)");
while ($row = $result->fetch_assoc()) { $no_show_by_day[] = $row; }

// 3. Visit count by day of week
$visits_by_day = [];
$result = $mysqli->query("SELECT 
    DAYNAME(visit_date) as day_name,
    COUNT(*) as cnt
FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'
GROUP BY DAYNAME(visit_date) ORDER BY DAYOFWEEK(visit_date)");
while ($row = $result->fetch_assoc()) { $visits_by_day[] = $row; }

// 4. Visit type distribution
$visit_types = [];
$result = $mysqli->query("SELECT visit_reason, COUNT(*) as cnt 
    FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date' AND visit_reason IS NOT NULL
    GROUP BY visit_reason ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $visit_types[] = $row; }

// 5. Peak hours (by hour of day)
$peak_hours = [];
$result = $mysqli->query("SELECT HOUR(visit_date) as hour, COUNT(*) as cnt 
    FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY HOUR(visit_date) ORDER BY hour");
while ($row = $result->fetch_assoc()) { $peak_hours[] = $row; }

// 6. Monthly visit count
$monthly_count = [];
$result = $mysqli->query("SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as cnt
    FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $monthly_count[] = $row; }

// 7. Capacity utilization
$capacity_util = [];
$result = $mysqli->query("SELECT 
    DATE_FORMAT(visit_date, '%Y-%m') as month,
    COUNT(*) as visits,
    ROUND(COUNT(*) / 22, 1) as avg_daily
FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $capacity_util[] = $row; }
?>
<style>
    .flow-card { text-align: center; padding: 1.2rem; border-radius: 12px; border: 1px solid var(--border); }
    .flow-card .big { font-size: 2rem; font-weight: 800; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header"><h1 class="page-title">⏱ تحليلات تدفق العيادة</h1><p class="page-subtitle">Clinic Flow Analytics — المواعيد، أوقات الذروة، الطاقة الاستيعابية</p></div>

<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="flow-card"><div class="big" style="color:var(--teal);"><?php echo $no_show['total'] ?: 0; ?></div><div>إجمالي المواعيد</div></div>
    <div class="flow-card"><div class="big" style="color:var(--red);"><?php echo $no_show['no_shows'] ?: 0; ?></div><div>لم يحضروا</div></div>
    <div class="flow-card"><div class="big" style="color:var(--orange);"><?php echo $no_show['rate'] ?: 0; ?>%</div><div>نسبة عدم الحضور</div></div>
    <div class="flow-card"><div class="big" style="color:var(--blue);"><?php echo array_sum(array_column($visits_by_day, 'cnt')) ?: 0; ?></div><div>إجمالي الزيارات</div></div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">📊 الزيارات حسب اليوم</div></div>
        <div style="height:200px;"><canvas id="visitsByDayChart" width="400" height="200"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">🚫 عدم الحضور حسب اليوم</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <?php foreach ($no_show_by_day as $d): ?>
            <div style="display:flex;justify-content:space-between;padding:6px;background:var(--bg-input);border-radius:6px;font-size:13px;">
                <span><?php echo $d['day_name']; ?></span>
                <span><strong style="color:<?php echo $d['rate'] > 20 ? 'var(--red)' : ($d['rate'] > 10 ? 'var(--orange)' : 'var(--green)'); ?>"><?php echo $d['rate']; ?>%</strong> (<?php echo $d['no_shows']; ?>/<?php echo $d['total']; ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">📋 أنواع الزيارات</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <?php foreach ($visit_types as $vt): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
                <span><?php echo $vt['visit_reason']; ?></span>
                <span class="badge badge-info"><?php echo $vt['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">🕐 أوقات الذروة (حسب الساعة)</div></div>
        <div style="height:200px;"><canvas id="peakHoursChart" width="400" height="200"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">📈 الطاقة الاستيعابية (متوسط يومي)</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <?php foreach ($capacity_util as $cu): ?>
            <div style="display:flex;justify-content:space-between;padding:6px;background:var(--bg-input);border-radius:6px;font-size:13px;">
                <span><?php echo $cu['month']; ?></span>
                <span><strong><?php echo $cu['visits']; ?></strong> زيارة | <span style="color:var(--teal);"><?php echo $cu['avg_daily']; ?>/يوم</span></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dayData = <?php echo json_encode($visits_by_day, JSON_UNESCAPED_UNICODE); ?>;
    if (dayData.length > 0) {
        new ClinicChart('visitsByDayChart').drawBarChart(
            dayData.map(d => d.day_name.slice(0,3)),
            dayData.map(d => parseInt(d.cnt)),
            { colors: ['#0a7e6e', '#3b82f6', '#f59e0b', '#8b5cf6', '#10b981', '#ec4899', '#6366f1'] }
        );
    }
    const peakData = <?php echo json_encode($peak_hours, JSON_UNESCAPED_UNICODE); ?>;
    if (peakData.length > 0) {
        new ClinicChart('peakHoursChart').drawLineChart(
            peakData.map(d => d.hour + ':00'),
            peakData.map(d => parseInt(d.cnt)),
            { lineColor: '#f59e0b', fillColor: 'rgba(245,158,11,0.08)' }
        );
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
