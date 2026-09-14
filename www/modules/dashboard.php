<?php
/**
 * Dashboard Page
 */
$page_title = 'لوحة التحكم | Dashboard';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Ensure appointments table exists (for dashboard widgets)
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

// Fetch statistics
$stats = [];

// Total patients
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM patients WHERE is_active = 1");
$stats['total_patients'] = $result->fetch_assoc()['cnt'];

// Total visits
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM visits");
$stats['total_visits'] = $result->fetch_assoc()['cnt'];

// Type 1 vs Type 2 diabetes
$result = $mysqli->query("SELECT diabetes_type, COUNT(*) as cnt FROM medical_history GROUP BY diabetes_type");
$diabetes_types = [];
while ($row = $result->fetch_assoc()) {
    $diabetes_types[$row['diabetes_type']] = $row['cnt'];
}

// Active wounds (wounds that haven't healed 100%)
$result = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v 
                          JOIN foot_ulcers fu ON v.visit_id = fu.visit_id 
                          LEFT JOIN outcomes o ON v.visit_id = o.visit_id
                          WHERE (o.improvement_percentage IS NULL OR o.improvement_percentage < 100)");
$stats['active_wounds'] = $result->fetch_assoc()['cnt'];

// High risk patients (Wagner >= 3)
$result = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v 
                          JOIN foot_assessments fa ON v.visit_id = fa.visit_id 
                          WHERE fa.wagner_grade >= 3");
$stats['high_risk'] = $result->fetch_assoc()['cnt'];

// Recent visits
$recent = $mysqli->query("SELECT v.*, p.full_name, p.file_number 
                          FROM visits v 
                          JOIN patients p ON v.patient_id = p.patient_id 
                          ORDER BY v.created_at DESC LIMIT 5");

// Total amputations
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes 
                          WHERE current_amputation IS NOT NULL AND current_amputation != 'لا'");
$stats['amputations'] = $result->fetch_assoc()['cnt'];

// Today's visits
$today = date('Y-m-d');
$stmt_today = $mysqli->prepare("SELECT COUNT(*) as cnt FROM visits WHERE DATE(visit_date) = ?");
$stmt_today->bind_param('s', $today);
$stmt_today->execute();
$stats['today_visits'] = $stmt_today->get_result()->fetch_assoc()['cnt'];

// Healed cases
$result = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v 
                          JOIN outcomes o ON v.visit_id = o.visit_id 
                          WHERE o.improvement_percentage = 100");
$stats['healed'] = $result->fetch_assoc()['cnt'];

// ===== NEW: Today's Appointments =====
$todays_appointments = $mysqli->query("
    SELECT a.*, p.full_name, p.file_number, p.phone_primary
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_date = '$today' AND a.status NOT IN ('ملغي')
    ORDER BY a.appointment_time ASC
");

// ===== NEW: Risk Alert Summaries =====
// High HbA1c (latest reading > 7%)
$high_hba1c_count = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt
    FROM visits v
    JOIN blood_sugar_readings bsr ON v.visit_id = bsr.visit_id
    WHERE bsr.hba1c_value > 7
")->fetch_assoc()['cnt'];

// Missed visits (no visit in 60+ days)
$missed_visits_count = $mysqli->query("
    SELECT COUNT(*) as cnt FROM patients p
    WHERE p.is_active = 1
    AND NOT EXISTS (
        SELECT 1 FROM visits v
        WHERE v.patient_id = p.patient_id
        AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    )
")->fetch_assoc()['cnt'];

// Unhealed Wagner 3+ patients
$wagner3_count = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt
    FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fa.wagner_grade >= 3
    AND (o.improvement_percentage IS NULL OR o.improvement_percentage < 100)
")->fetch_assoc()['cnt'];

// Total pending appointments (next 7 days)
$week_appointments_count = $mysqli->query("
    SELECT COUNT(*) as cnt FROM appointments
    WHERE appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND status IN ('مؤكد', 'قيد الانتظار')
")->fetch_assoc()['cnt'];

// ===== NEW: Monthly Visit Trend (last 6 months) =====
$monthly_visits = $mysqli->query("
    SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as cnt
    FROM visits
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY month ASC
");
$months_labels = [];
$months_values = [];
while ($mv = $monthly_visits->fetch_assoc()) {
    $months_labels[] = $mv['month'];
    $months_values[] = (int)$mv['cnt'];
}
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">📊 لوحة التحكم</h1>
                <p class="page-subtitle">نظرة عامة على بيانات المركز - Sari Endocrinology & Diabetes Center</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #0a7e6e, #13a896);"></div>
                    <div class="stat-icon" style="background: #e0f5f2; color: #0a7e6e;">👥</div>
                    <div class="stat-value" style="color: #0a7e6e;"><?php echo $stats['total_patients']; ?></div>
                    <div class="stat-label">إجمالي المرضى</div>
                    <div class="stat-sub">Total Patients</div>
                </div>

                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #3b82f6, #1d4ed8);"></div>
                    <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">🩺</div>
                    <div class="stat-value" style="color: #1d4ed8;"><?php echo $stats['total_visits']; ?></div>
                    <div class="stat-label">إجمالي الزيارات</div>
                    <div class="stat-sub">Total Visits</div>
                </div>

                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #f59e0b, #d97706);"></div>
                    <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">📅</div>
                    <div class="stat-value" style="color: #d97706;"><?php echo $stats['today_visits']; ?></div>
                    <div class="stat-label">زيارات اليوم</div>
                    <div class="stat-sub">Today's Visits</div>
                </div>

                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #ef4444, #dc2626);"></div>
                    <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">🆘</div>
                    <div class="stat-value" style="color: #dc2626;"><?php echo $stats['high_risk']; ?></div>
                    <div class="stat-label">حالات خطرة (Wagner ≥ 3)</div>
                    <div class="stat-sub">High Risk</div>
                </div>

                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #8b5cf6, #7c3aed);"></div>
                    <div class="stat-icon" style="background: #f5f3ff; color: #8b5cf6;">🩹</div>
                    <div class="stat-value" style="color: #7c3aed;"><?php echo $stats['active_wounds']; ?></div>
                    <div class="stat-label">جروح نشطة</div>
                    <div class="stat-sub">Active Wounds</div>
                </div>

                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #10b981, #059669);"></div>
                    <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">✅</div>
                    <div class="stat-value" style="color: #059669;"><?php echo $stats['healed']; ?></div>
                    <div class="stat-label">تم شفاؤها</div>
                    <div class="stat-sub">Healed Cases</div>
                </div>

                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background: linear-gradient(90deg, #6b21a8, #9333ea);"></div>
                    <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">🦿</div>
                    <div class="stat-value" style="color: #9333ea;"><?php echo $stats['amputations']; ?></div>
                    <div class="stat-label">حالات البتر</div>
                    <div class="stat-sub">Amputations</div>
                </div>
            </div>

            <!-- Risk Alert Summary Cards -->
            <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:24px;">
                <div class="stat-card card-fade-in" onclick="location.href='<?php echo BASE_URL; ?>/modules/analytics/risk_alerts.php'" style="cursor:pointer;">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#ef4444,#dc2626);"></div>
                    <div class="stat-icon" style="background:#fef2f2;color:#ef4444;">🆘</div>
                    <div class="stat-value" style="color:#dc2626;font-size:20px;"><?php echo $wagner3_count; ?></div>
                    <div class="stat-label">Wagner ≥ 3 غير ملتئمة</div>
                    <div class="stat-sub">Critical Wounds</div>
                </div>
                <div class="stat-card card-fade-in" onclick="location.href='<?php echo BASE_URL; ?>/modules/analytics/diabetic_analytics.php'" style="cursor:pointer;">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
                    <div class="stat-icon" style="background:#fffbeb;color:#f59e0b;">🩸</div>
                    <div class="stat-value" style="color:#d97706;font-size:20px;"><?php echo $high_hba1c_count; ?></div>
                    <div class="stat-label">HbA1c مرتفع (&gt;7%)</div>
                    <div class="stat-sub">High HbA1c</div>
                </div>
                <div class="stat-card card-fade-in" onclick="location.href='<?php echo BASE_URL; ?>/modules/analytics/risk_alerts.php'" style="cursor:pointer;">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
                    <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;">🚫</div>
                    <div class="stat-value" style="color:#7c3aed;font-size:20px;"><?php echo $missed_visits_count; ?></div>
                    <div class="stat-label">لم يزوروا &gt;60 يوماً</div>
                    <div class="stat-sub">Missed Visits</div>
                </div>
                <div class="stat-card card-fade-in" onclick="location.href='<?php echo BASE_URL; ?>/modules/appointments/index.php'" style="cursor:pointer;">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#0ea5e9,#0284c7);"></div>
                    <div class="stat-icon" style="background:#eff6ff;color:#0ea5e9;">📅</div>
                    <div class="stat-value" style="color:#0284c7;font-size:20px;"><?php echo $week_appointments_count; ?></div>
                    <div class="stat-label">مواعيد هذا الأسبوع</div>
                    <div class="stat-sub">Weekly Appointments</div>
                </div>
            </div>

            <!-- Three column layout -->
            <div class="flex flex-wrap gap-4" style="margin-bottom:24px;">
                
                <!-- Diabetes Type Distribution -->
                <div class="card" style="flex: 1; min-width: 280px;">
                    <div class="card-header">
                        <div class="card-title">📊 توزيع أنواع السكري</div>
                    </div>
                    <div style="height:220px;">
                        <canvas id="diabetesChart" width="400" height="220"></canvas>
                    </div>
                </div>

                <!-- Monthly Visit Trend -->
                <div class="card" style="flex: 1; min-width: 280px;">
                    <div class="card-header">
                        <div class="card-title">📈 اتجاه الزيارات الشهرية</div>
                    </div>
                    <div style="height:220px;">
                        <canvas id="trendChart" width="400" height="220"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="flex: 1; min-width: 240px;">
                    <div class="card-header">
                        <div class="card-title">⚡ إجراءات سريعة</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <a href="<?php echo BASE_URL; ?>/modules/patients/add.php" class="btn btn-primary" style="justify-content:center;padding:10px;">
                            ➕ إضافة مريض جديد
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/visits/add.php" class="btn btn-secondary" style="justify-content:center;padding:10px;">
                            🩺 زيارة جديدة
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/appointments/index.php" class="btn btn-secondary" style="justify-content:center;padding:10px;">
                            📅 موعد جديد
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/analytics/risk_alerts.php" class="btn btn-secondary" style="justify-content:center;padding:10px;">
                            ⚠️ تنبيهات الخطر
                        </a>
                    </div>
                </div>
            </div>

            <!-- Two column: Recent Visits + Today's Appointments -->
            <div class="flex flex-wrap gap-4" style="margin-bottom:24px;">

                <!-- Recent Visits -->
                <div class="card" style="flex:1;min-width:350px;">
                    <div class="card-header">
                        <div class="card-title">🕐 آخر الزيارات</div>
                        <a href="<?php echo BASE_URL; ?>/modules/patients/index.php" class="btn btn-sm btn-primary">عرض الكل</a>
                    </div>
                    <div class="table-container">
                        <table style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th>رقم الملف</th>
                                    <th>اسم المريض</th>
                                    <th>التاريخ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent->num_rows === 0): ?>
                                    <tr><td colspan="4" style="color:var(--text-muted);">لا توجد زيارات بعد</td></tr>
                                <?php else: ?>
                                    <?php while ($v = $recent->fetch_assoc()): ?>
                                    <tr class="clickable-row" data-href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $v['visit_id']; ?>">
                                        <td><?php echo escape_output($v['file_number']); ?></td>
                                        <td style="font-weight:600;"><?php echo escape_output($v['full_name']); ?></td>
                                        <td><?php echo $v['visit_date']; ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $v['visit_id']; ?>" class="btn btn-sm btn-primary">عرض</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Today's Appointments Widget -->
                <div class="card" style="flex:1;min-width:350px;">
                    <div class="card-header">
                        <div class="card-title">📅 مواعيد اليوم</div>
                        <a href="<?php echo BASE_URL; ?>/modules/appointments/index.php" class="btn btn-sm btn-primary">عرض الكل</a>
                    </div>
                    <?php if ($todays_appointments->num_rows === 0): ?>
                        <div style="text-align:center;padding:24px 0;color:var(--text-muted);">
                            <div style="font-size:32px;margin-bottom:8px;">📅</div>
                            <p>لا توجد مواعيد اليوم</p>
                            <a href="<?php echo BASE_URL; ?>/modules/appointments/index.php" class="btn btn-sm btn-primary mt-2">➕ إضافة موعد</a>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <?php while ($a = $todays_appointments->fetch_assoc()): 
                                $status_color = [
                                    'مؤكد' => 'var(--green)',
                                    'قيد الانتظار' => 'var(--orange)',
                                    'مكتمل' => 'var(--blue)',
                                    'لم يحضر' => 'var(--red)'
                                ][$a['status']] ?? 'var(--gray)';
                                $status_bg = [
                                    'مؤكد' => '#d1fae5',
                                    'قيد الانتظار' => '#fef3c7',
                                    'مكتمل' => '#dbeafe',
                                    'لم يحضر' => '#fee2e2'
                                ][$a['status']] ?? '#f1f5f9';
                            ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:var(--bg-input);border-radius:8px;border-right:3px solid <?php echo $status_color; ?>;">
                                <div style="flex:1;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <strong style="font-size:15px;color:var(--text-heading);"><?php echo escape_output($a['full_name']); ?></strong>
                                        <span style="font-size:12px;color:var(--text-muted);">[<?php echo $a['file_number']; ?>]</span>
                                    </div>
                                    <div style="display:flex;gap:8px;margin-top:2px;font-size:12px;">
                                        <span style="color:var(--teal);font-weight:700;"><?php echo date('H:i', strtotime($a['appointment_time'])); ?></span>
                                        <span style="color:var(--text-muted);"><?php echo $a['appointment_type']; ?></span>
                                        <?php if ($a['phone_primary']): ?>
                                            <span style="color:var(--text-light);">📞 <?php echo escape_output($a['phone_primary']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;background:<?php echo $status_bg; ?>;color:<?php echo $status_color; ?>;white-space:nowrap;">
                                    <?php echo $a['status']; ?>
                                </span>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/modules/appointments/index.php" class="btn btn-sm btn-secondary mt-2" style="width:100%;justify-content:center;">📅 فتح التقويم الكامل</a>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Diabetes type distribution chart
    const canvas = document.getElementById('diabetesChart');
    if (canvas) {
        const data = [
            <?php foreach ($diabetes_types as $type => $count): ?>
            { label: <?php echo json_encode($type ?: 'غير محدد', JSON_UNESCAPED_UNICODE); ?>, value: <?php echo $count; ?> },
            <?php endforeach; ?>
        ];
        if (data.length > 0) {
            const chart = new ClinicChart('diabetesChart');
            chart.drawPieChart(data, { donut: true });
        } else {
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#94a3b8';
            ctx.font = '14px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('لا توجد بيانات كافية', canvas.width/2, canvas.height/2);
        }
    }

    // Monthly visit trend chart
    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas) {
        const labels = <?php echo json_encode($months_labels, JSON_UNESCAPED_UNICODE); ?>;
        const values = <?php echo json_encode($months_values); ?>;
        if (values.length > 0) {
            const chart = new ClinicChart('trendChart');
            chart.drawLineChart(labels, values, {
                lineColor: '#13a896',
                fillColor: 'rgba(19, 168, 150, 0.1)',
                lineWidth: 3
            });
        } else {
            const ctx = trendCanvas.getContext('2d');
            ctx.fillStyle = '#94a3b8';
            ctx.font = '14px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('لا توجد بيانات كافية', trendCanvas.width/2, trendCanvas.height/2);
        }
    }
});
</script>
