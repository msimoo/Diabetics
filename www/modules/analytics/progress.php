<?php
/**
 * Healing Progress Tracking
 * Tracks wound healing progress over time with charts
 */
$page_title = 'تتبع التقدم | Healing Progress';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$patient = null;
$dates = [];
$values = [];

if ($patient_id) {
    $stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ? AND is_active = 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    // Get healing progress data
    $stmt = $mysqli->prepare("SELECT v.visit_date, o.improvement_percentage, o.healing_date
                              FROM visits v
                              JOIN outcomes o ON v.visit_id = o.visit_id
                              WHERE v.patient_id = ?
                              ORDER BY v.visit_date ASC");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $progress = $stmt->get_result();

    while ($row = $progress->fetch_assoc()) {
        $dates[] = $row['visit_date'];
        $values[] = (int)$row['improvement_percentage'];
    }

    // Get HbA1c trend
    $stmt = $mysqli->prepare("SELECT v.visit_date, bs.hba1c_value
                              FROM visits v
                              JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
                              WHERE v.patient_id = ? AND bs.hba1c_value IS NOT NULL
                              ORDER BY v.visit_date ASC");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $hba1c_data = $stmt->get_result();
    $hba1c_dates = [];
    $hba1c_values = [];
    while ($row = $hba1c_data->fetch_assoc()) {
        $hba1c_dates[] = $row['visit_date'];
        $hba1c_values[] = (float)$row['hba1c_value'];
    }
}
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            <div class="page-header">
                <h1 class="page-title">📈 تتبع تقدم التئام الجروح</h1>
                <p class="page-subtitle">Wound Healing Progress Tracking</p>
            </div>

            <?php if (!$patient_id): ?>
            <div class="card">
                <div class="card-header"><div class="card-title">🔍 اختيار مريض</div></div>
                <div class="field">
                    <label>اختر المريض</label>
                    <select id="patientSelect" onchange="if(this.value) window.location.href='?patient_id='+this.value">
                        <option value="">-- اختر المريض --</option>
                        <?php 
                        $patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name");
                        while ($p = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $p['patient_id']; ?>">
                            <?php echo escape_output($p['full_name']); ?> — 📁 <?php echo escape_output($p['file_number']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Patient Info -->
            <div class="card mb-4" style="background:linear-gradient(135deg,var(--teal),var(--teal-light));color:#fff;">
                <div class="flex flex-wrap gap-3 items-center justify-between">
                    <div>
                        <h2 style="font-family:'Cairo',sans-serif;font-size:20px;font-weight:900;">
                            👤 <?php echo escape_output($patient['full_name'] ?? ''); ?>
                        </h2>
                        <p style="opacity:0.85;">📁 <?php echo escape_output($patient['file_number'] ?? ''); ?></p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary" style="background:rgba(255,255,255,0.2);color:#fff;border:none;">
                        👤 ملف المريض
                    </a>
                </div>
            </div>

            <!-- Healing Progress Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title">📊 تقدم التئام الجرح</div>
                </div>
                <div style="height:300px;">
                    <canvas id="progressChart" width="800" height="300"></canvas>
                </div>
                <?php if (empty($values)): ?>
                <p style="color:#94a3b8;text-align:center;padding:20px;">لا توجد بيانات تقدم متاحة</p>
                <?php endif; ?>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card card-fade-in">
                    <div class="stat-value" style="color:var(--teal);font-size:28px;">
                        <?php echo count($values) > 0 ? end($values) . '%' : '—'; ?>
                    </div>
                    <div class="stat-label">آخر نسبة تحسن</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-value" style="color:var(--blue);font-size:28px;">
                        <?php echo count($dates) > 0 ? $dates[0] . ' → ' . end($dates) : '—'; ?>
                    </div>
                    <div class="stat-label">فترة المتابعة</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-value" style="color:var(--orange);font-size:28px;">
                        <?php echo count($dates); ?>
                    </div>
                    <div class="stat-label">عدد الزيارات</div>
                </div>
            </div>

            <!-- HbA1c Trend -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title">📊 تطور HbA1c عبر الزمن</div>
                </div>
                <div style="height:260px;">
                    <canvas id="hba1cChart" width="800" height="260"></canvas>
                </div>
                <?php if (empty($hba1c_values)): ?>
                <p style="color:#94a3b8;text-align:center;padding:20px;">لا توجد قراءات HbA1c متاحة</p>
                <?php endif; ?>
            </div>

            <!-- Progress Data Table -->
            <?php if (!empty($dates)): ?>
            <div class="card">
                <div class="card-header"><div class="card-title">📋 تفاصيل التقدم</div></div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>تاريخ الزيارة</th>
                                <th>نسبة التحسن</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < count($dates); $i++): ?>
                            <tr>
                                <td><?php echo $dates[$i]; ?></td>
                                <td><strong><?php echo $values[$i]; ?>%</strong></td>
                                <td>
                                    <?php if ($values[$i] >= 100): ?>
                                        <span class="badge badge-success">✅ التئام كامل</span>
                                    <?php elseif ($values[$i] >= 75): ?>
                                        <span class="badge badge-info">🟢 تحسن كبير</span>
                                    <?php elseif ($values[$i] >= 50): ?>
                                        <span class="badge badge-warning">🟡 تحسن متوسط</span>
                                    <?php elseif ($values[$i] >= 25): ?>
                                        <span class="badge badge-warning">🟠 تحسن طفيف</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">🔴 لا تحسن</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
                <?php 
                $latest_vid = 0;
                if ($patient_id) {
                    $vid_stmt = $mysqli->prepare("SELECT MAX(visit_id) as vid FROM visits WHERE patient_id = ?");
                    $vid_stmt->bind_param('i', $patient_id);
                    $vid_stmt->execute();
                    $latest_vid = (int)($vid_stmt->get_result()->fetch_assoc()['vid'] ?? 0);
                }
                ?>
                <a href="<?php echo BASE_URL; ?>/modules/assessments/outcomes.php?visit_id=<?php echo $latest_vid; ?>" class="btn btn-primary">📈 تسجيل نتائج جديدة</a>
            </div>
            <?php endif; ?>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($values)): ?>
    // Progress chart
    const pc = new ClinicChart('progressChart');
    pc.drawLineChart(
        <?php echo json_encode($dates); ?>,
        <?php echo json_encode($values); ?>,
        { lineColor: '#10b981', fillColor: 'rgba(16, 185, 129, 0.1)' }
    );
    <?php endif; ?>

    <?php if (!empty($hba1c_values)): ?>
    // HbA1c chart
    const hc = new ClinicChart('hba1cChart');
    hc.drawLineChart(
        <?php echo json_encode($hba1c_dates); ?>,
        <?php echo json_encode($hba1c_values); ?>,
        { lineColor: '#ef4444', fillColor: 'rgba(239, 68, 68, 0.1)' }
    );
    <?php endif; ?>
});
</script>
</div>
