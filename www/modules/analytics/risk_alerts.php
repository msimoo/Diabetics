<?php
/**
 * Risk Alerts & Stratification
 */
$page_title = 'تنبيهات الخطر | Risk Alerts';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

/**
 * Calculate risk score for a patient
 */
function calculate_risk_score($patient_id, $mysqli) {
    $score = 0;
    $alerts = [];
    $class = 'منخفض';
    $color = '#10b981';

    $query = "SELECT 
        mh.smoking_status,
        fa.right_sensation, fa.left_sensation,
        fa.right_pulse, fa.left_pulse,
        fa.wagner_grade,
        bs.hba1c_value,
        fu.wound_condition
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    WHERE p.patient_id = ?
    ORDER BY v.visit_date DESC
    LIMIT 1";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) return ['score' => 0, 'class' => 'غير معروف', 'color' => '#94a3b8', 'alerts' => ['لا توجد بيانات كافية']];

    // 1. Smoking (+20)
    if (($data['smoking_status'] ?? '') === 'مدخن') {
        $score += 20; $alerts[] = '🚬 مريض مدخن — خطر البتر مرتفع';
    }

    // 2. No sensation (+25)
    if (($data['right_sensation'] ?? '') === 'معدوم' || ($data['left_sensation'] ?? '') === 'معدوم') {
        $score += 25; $alerts[] = '⚠️ فقدان الإحساس في القدم — خطر الإصابة بالقرحة';
    }

    // 3. No pulse (+25)
    if (($data['right_pulse'] ?? '') === 'معدوم' || ($data['left_pulse'] ?? '') === 'معدوم') {
        $score += 25; $alerts[] = '🚨 انعدام النبض الطرفي — خطر نقص التروية';
    }

    // 4. HbA1c high (+15 per 1% above 7, max 30)
    if (!empty($data['hba1c_value']) && $data['hba1c_value'] > 7) {
        $extra = ($data['hba1c_value'] - 7) * 15;
        $score += min($extra, 30);
        $alerts[] = '📈 HbA1c مرتفع (' . $data['hba1c_value'] . '%) — السكري غير مضبوط';
    }

    // 5. Wagner >= 3 (+30)
    if (!empty($data['wagner_grade']) && $data['wagner_grade'] >= 3) {
        $score += 30;
        $alerts[] = '🆘 درجة Wagner ' . $data['wagner_grade'] . ' — خطر بتر مرتفع جداً';
    }

    // 6. Infected wound (+15)
    if (in_array($data['wound_condition'] ?? '', ['متسخة', 'صديد'])) {
        $score += 15;
        $alerts[] = '🦠 الجرح ملتهب — يحتاج تدخل فوري';
    }

    // Risk classification
    if ($score >= 100) { $class = 'شديد جداً'; $color = '#7f1d1d'; }
    elseif ($score >= 70) { $class = 'مرتفع'; $color = '#dc2626'; }
    elseif ($score >= 40) { $class = 'متوسط'; $color = '#f59e0b'; }
    else { $class = 'منخفض'; $color = '#10b981'; }

    return ['score' => $score, 'class' => $class, 'color' => $color, 'alerts' => $alerts];
}

// Get all patients with their latest visit and risk data
$patients = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, p.age, p.gender,
                           (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
                           FROM patients p WHERE p.is_active = 1
                           ORDER BY p.created_at DESC");

$risk_patients = [];
while ($p = $patients->fetch_assoc()) {
    $p['risk'] = calculate_risk_score($p['patient_id'], $mysqli);
    $risk_patients[] = $p;
}

// Sort by risk score (highest first)
usort($risk_patients, function($a, $b) {
    return $b['risk']['score'] - $a['risk']['score'];
});

// === RISK MATRIX DATA ===

// Risk distribution counts
$risk_counts = ['منخفض' => 0, 'متوسط' => 0, 'مرتفع' => 0, 'شديد جداً' => 0];
foreach ($risk_patients as $p) {
    $cls = $p['risk']['class'];
    if (isset($risk_counts[$cls])) $risk_counts[$cls]++;
}

// Patients with no visits (inactive tracking)
$no_visits = $mysqli->query("SELECT COUNT(*) as c FROM patients p WHERE p.is_active = 1 AND NOT EXISTS (SELECT 1 FROM visits v WHERE v.patient_id = p.patient_id)")->fetch_assoc()['c'];

// Smokers count
$smokers = $mysqli->query("SELECT COUNT(*) as c FROM medical_history WHERE smoking_status = 'مدخن'")->fetch_assoc()['c'];

// High HbA1c (>9%) patients
$high_hba1c = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id WHERE bs.hba1c_value > 9")->fetch_assoc()['c'];

// Predictive insights
$predictive_alerts = [];
if ($smokers > 0) $predictive_alerts[] = ['icon' => '🚬', 'msg' => "$smokers مريض مدخن — خطر البتر مرتفع", 'color' => '#dc2626'];
if ($high_hba1c > 0) $predictive_alerts[] = ['icon' => '📈', 'msg' => "$high_hba1c مريض HbA1c > 9% — خطر مضاعفات", 'color' => '#f59e0b'];
if ($no_visits > 0) $predictive_alerts[] = ['icon' => '⚠️', 'msg' => "$no_visits مريض بدون زيارات — قد يكونون منقطعين", 'color' => '#3b82f6'];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="page-title">⚠️ تنبيهات الخطر</h1>
        <p class="page-subtitle">تصنيف آلي لخطورة المرضى مع مصفوفة المخاطر والتنبؤات</p>
    </div>
    <div style="display:flex;gap:12px;align-items:center;font-size:13px;">
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#10b981;"></span> منخفض</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#f59e0b;"></span> متوسط</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#dc2626;"></span> مرتفع</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#7f1d1d;"></span> شديد جداً</span>
    </div>
</div>

<!-- Risk Matrix Cards -->
<div class="stats-grid">
    <div class="stat-card card-fade-in" style="border-<?php echo $risk_counts['شديد جداً'] > 0 ? 'right:3px solid #7f1d1d;' : ''; ?>">
        <div class="stat-bar" style="background:linear-gradient(90deg,#7f1d1d,#dc2626);"></div>
        <div class="stat-value" style="color:#7f1d1d;font-size:28px;"><?php echo $risk_counts['شديد جداً']; ?></div>
        <div class="stat-label">🔴 شديد جداً</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#dc2626,#ef4444);"></div>
        <div class="stat-value" style="color:#dc2626;font-size:28px;"><?php echo $risk_counts['مرتفع']; ?></div>
        <div class="stat-label">🟠 مرتفع</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
        <div class="stat-value" style="color:#d97706;font-size:28px;"><?php echo $risk_counts['متوسط']; ?></div>
        <div class="stat-label">🟡 متوسط</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
        <div class="stat-value" style="color:#059669;font-size:28px;"><?php echo $risk_counts['منخفض']; ?></div>
        <div class="stat-label">🟢 منخفض</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
        <div class="stat-value" style="color:#1d4ed8;font-size:28px;"><?php echo $no_visits; ?></div>
        <div class="stat-label">👤 بدون زيارات</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
        <div class="stat-value" style="color:#7c3aed;font-size:28px;"><?php echo $smokers; ?></div>
        <div class="stat-label">🚬 مدخنون</div>
    </div>
</div>

<!-- Predictive Insights -->
<?php if ($predictive_alerts): ?>
<div class="card mb-4" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #f59e0b;">
    <div class="card-header" style="border-bottom-color:#f59e0b;">
        <div class="card-title" style="color:#d97706;">🔮 تنبيهات استباقية — مؤشرات خطر عالية</div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;">
        <?php foreach ($predictive_alerts as $pa): ?>
        <div style="flex:1;min-width:200px;padding:12px 16px;background:white;border-radius:10px;border-right:3px solid <?php echo $pa['color']; ?>;">
            <div style="font-size:14px;color:<?php echo $pa['color']; ?>;">
                <?php echo $pa['icon']; ?> <?php echo $pa['msg']; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Risk Distribution Chart -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">📊 مصفوفة توزيع المخاطر</div></div>
        <div style="height:200px;"><canvas id="riskMatrixChart" width="400" height="200"></canvas></div>
    </div>
    <div class="card" style="flex:2;min-width:400px;">
        <div class="card-header"><div class="card-title">📋 قائمة المرضى حسب درجة الخطورة</div></div>
        <div class="table-container">
            <table>
                <thead><tr>
                    <th>رقم الملف</th>
                    <th>المريض</th>
                    <th>آخر زيارة</th>
                    <th>درجة الخطورة</th>
                    <th>التصنيف</th>
                    <th>أهم التنبيهات</th>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($risk_patients)): ?>
                    <tr><td colspan="7" style="color:#94a3b8;padding:40px;">لا توجد بيانات مرضى</td></tr>
                    <?php else: ?>
                        <?php foreach ($risk_patients as $p): ?>
                        <?php $risk = $p['risk']; ?>
                        <tr class="clickable-row" data-href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>">
                            <td><?php echo escape_output($p['file_number']); ?></td>
                            <td><strong><?php echo escape_output($p['full_name']); ?></strong></td>
                            <td><?php echo $p['last_visit'] ?: '—'; ?></td>
                            <td>
                                <div style="position:relative;height:6px;background:#e2e8f0;border-radius:3px;width:80px;">
                                    <div style="position:absolute;left:0;top:0;height:100%;width:<?php echo min($risk['score'], 100); ?>%;background:<?php echo $risk['color']; ?>;border-radius:3px;"></div>
                                </div>
                                <strong style="font-size:14px;color:<?php echo $risk['color']; ?>;"><?php echo $risk['score']; ?></strong>
                            </td>
                            <td>
                                <span style="display:inline-block;padding:4px 12px;border-radius:20px;background:<?php echo $risk['color']; ?>20;color:<?php echo $risk['color']; ?>;font-weight:700;font-size:12px;">
                                    <?php echo $risk['class']; ?>
                                </span>
                            </td>
                            <td style="font-size:12px;text-align:right;">
                                <?php $first_alerts = array_slice($risk['alerts'], 0, 2); ?>
                                <?php foreach ($first_alerts as $a): ?>
                                    <div style="color:<?php echo $risk['color']; ?>;"><?php echo $a; ?></div>
                                <?php endforeach; ?>
                                <?php if (count($risk['alerts']) > 2): ?>
                                    <div style="color:#94a3b8;">+<?php echo count($risk['alerts']) - 2; ?> تنبيهات أخرى</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/modules/analytics/progress.php?patient_id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-primary">📈 تتبع</a>
                                <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-secondary">عرض</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Risk matrix chart
    const rc = new ClinicChart('riskMatrixChart');
    rc.drawPieChart([
        { label: 'شديد جداً', value: <?php echo $risk_counts['شديد جداً']; ?> },
        { label: 'مرتفع', value: <?php echo $risk_counts['مرتفع']; ?> },
        { label: 'متوسط', value: <?php echo $risk_counts['متوسط']; ?> },
        { label: 'منخفض', value: <?php echo $risk_counts['منخفض']; ?> }
    ], { 
        colors: ['#7f1d1d', '#dc2626', '#f59e0b', '#10b981'],
        donut: true 
    });
});
</script>
</div>
