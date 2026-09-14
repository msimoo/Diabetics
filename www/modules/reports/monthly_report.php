<?php
/**
 * Monthly Report
 * Generates a comprehensive monthly clinic report
 */
$page_title = 'التقرير الشهري | Monthly Report';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Date filtering
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Arabic month names
$arabic_months = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];

// Monthly stats
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

// 1. New patients this month
$stmt = $mysqli->prepare("SELECT COUNT(*) as c FROM patients WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND is_active = 1");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$new_patients = $stmt->get_result()->fetch_assoc()['c'];

// 2. Visits this month
$stmt = $mysqli->prepare("SELECT COUNT(*) as c FROM visits WHERE MONTH(visit_date) = ? AND YEAR(visit_date) = ?");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$monthly_visits = $stmt->get_result()->fetch_assoc()['c'];

// 3. New ulcers this month
$stmt = $mysqli->prepare("SELECT COUNT(*) as c FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id WHERE MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ?");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$new_ulcers = $stmt->get_result()->fetch_assoc()['c'];

// 4. Healed this month
$stmt = $mysqli->prepare("SELECT COUNT(*) as c FROM outcomes WHERE MONTH(healing_date) = ? AND YEAR(healing_date) = ?");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$healed_month = $stmt->get_result()->fetch_assoc()['c'];

// 5. Amputations this month
$stmt = $mysqli->prepare("SELECT COUNT(*) as c FROM outcomes WHERE MONTH(current_amputation_date) = ? AND YEAR(current_amputation_date) = ? AND current_amputation IS NOT NULL AND current_amputation != 'لا'");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$amputations_month = $stmt->get_result()->fetch_assoc()['c'];

// 6. Top causes this month
$stmt = $mysqli->prepare("SELECT fu.initial_cause, COUNT(*) as c FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id WHERE MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ? AND fu.initial_cause IS NOT NULL GROUP BY fu.initial_cause ORDER BY c DESC LIMIT 5");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$top_causes = $stmt->get_result();

// 7. Diabetes type distribution (all time)
$diabetes_types = $mysqli->query("SELECT diabetes_type, COUNT(*) as c FROM medical_history GROUP BY diabetes_type ORDER BY c DESC");

// 8. Recent visits this month
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ? ORDER BY v.visit_date DESC LIMIT 10");
$stmt->bind_param('ii', $month, $year);
$stmt->execute();
$recent_visits = $stmt->get_result();

// 9. Total patients
$total_patients = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <!-- Month Selector -->
            <div class="flex flex-wrap gap-3 items-center justify-between mb-4">
                <div>
                    <h1 class="page-title">📋 التقرير الشهري</h1>
                    <p class="page-subtitle">Monthly Report - <?php echo $arabic_months[$month]; ?> <?php echo $year; ?></p>
                </div>
                <div class="flex gap-2">
                    <form method="get" class="flex gap-2 items-center">
                        <select name="month" class="field" style="padding:6px 12px;border:1px solid var(--border);border-radius:8px;font-family:'Tajawal',sans-serif;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>><?php echo $arabic_months[$m]; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="field" style="padding:6px 12px;border:1px solid var(--border);border-radius:8px;font-family:'Tajawal',sans-serif;">
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">عرض</button>
                        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ طباعة</button>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div>
                    <div class="stat-icon" style="background:#e0f5f2;color:#0a7e6e;">👥</div>
                    <div class="stat-value" style="color:#0a7e6e;"><?php echo $new_patients; ?></div>
                    <div class="stat-label">مرضى جدد هذا الشهر</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
                    <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">🩺</div>
                    <div class="stat-value" style="color:#1d4ed8;"><?php echo $monthly_visits; ?></div>
                    <div class="stat-label">الزيارات هذا الشهر</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#ec4899,#db2777);"></div>
                    <div class="stat-icon" style="background:#fdf2f8;color:#ec4899;">🩹</div>
                    <div class="stat-value" style="color:#db2777;"><?php echo $new_ulcers; ?></div>
                    <div class="stat-label">جروح جديدة</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
                    <div class="stat-icon" style="background:#ecfdf5;color:#10b981;">✅</div>
                    <div class="stat-value" style="color:#059669;"><?php echo $healed_month; ?></div>
                    <div class="stat-label">تم شفاؤها</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#ef4444,#dc2626);"></div>
                    <div class="stat-icon" style="background:#fef2f2;color:#ef4444;">🦿</div>
                    <div class="stat-value" style="color:#dc2626;"><?php echo $amputations_month; ?></div>
                    <div class="stat-label">حالات بتر</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
                    <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;">🏥</div>
                    <div class="stat-value" style="color:#7c3aed;"><?php echo $total_patients; ?></div>
                    <div class="stat-label">إجمالي المرضى</div>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mb-4">
                <!-- Top Causes -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">⚠️ أكثر أسباب الإصابة</div></div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php if ($top_causes->num_rows === 0): ?>
                        <p style="color:#94a3b8;">لا توجد بيانات</p>
                        <?php else: while ($cause = $top_causes->fetch_assoc()): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:13px;"><?php echo escape_output($cause['initial_cause']); ?></span>
                            <span class="badge badge-info"><?php echo $cause['c']; ?></span>
                        </div>
                        <?php endwhile; endif; ?>
                    </div>
                </div>

                <!-- Diabetes Distribution -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">📊 توزيع أنواع السكري</div></div>
                    <div style="height:200px;">
                        <canvas id="diabetesChartMonth" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Visits Table -->
            <div class="card">
                <div class="card-header"><div class="card-title">🕐 الزيارات الأخيرة هذا الشهر</div></div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>التاريخ</th><th>المريض</th><th>رقم الملف</th><th>السبب</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_visits->num_rows === 0): ?>
                            <tr><td colspan="5" style="color:#94a3b8;">لا توجد زيارات هذا الشهر</td></tr>
                            <?php else: while ($v = $recent_visits->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $v['visit_date']; ?></td>
                                <td><?php echo escape_output($v['full_name']); ?></td>
                                <td><?php echo escape_output($v['file_number']); ?></td>
                                <td><?php echo $v['visit_reason'] ?: '—'; ?></td>
                                <td><a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $v['visit_id']; ?>" class="btn btn-sm btn-primary">عرض</a></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const diabetesData = <?php 
        $data = [];
        while ($d = $diabetes_types->fetch_assoc()) {
            $data[] = ['label' => $d['diabetes_type'] ?: 'غير محدد', 'value' => (int)$d['c']];
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ?>;
    if (diabetesData.length > 0) {
        const chart = new ClinicChart('diabetesChartMonth');
        chart.drawPieChart(diabetesData, { donut: true });
    }
});
</script>
</div>
