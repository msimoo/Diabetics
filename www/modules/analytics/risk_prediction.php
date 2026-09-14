<?php
/**
 * Predictive Risk Model
 * Forecasts patient risk of complications within 30/60/90 day windows
 * Uses historical trends, clinical indicators, and visit patterns
 */
$page_title = 'النموذج التنبؤي | Risk Prediction';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

/**
 * Calculate prediction score for a patient based on multiple risk factors
 */
function predict_patient_risk($patient_id, $mysqli) {
    $base_score = 0;
    $factors = [];
    $trends = [];

    // 1. Latest clinical data
    $query = "SELECT 
        mh.smoking_status,
        fa.wagner_grade, fa.right_sensation, fa.left_sensation,
        fa.right_pulse, fa.left_pulse,
        bs.hba1c_value,
        fu.wound_condition, fu.initial_cause,
        o.improvement_percentage,
        v.visit_date
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.patient_id = ?
    ORDER BY v.visit_date DESC LIMIT 1";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        return ['score' => 0, 'class' => 'غير معروف', 'color' => '#94a3b8', 
                'factors' => [], 'trends' => [], 'forecast_30d' => '—', 'forecast_60d' => '—', 'forecast_90d' => '—'];
    }

    // === RISK FACTORS ===

    // HbA1c trend (high HbA1c = high future risk)
    $hba1c = $data['hba1c_value'] ?? 0;
    if ($hba1c > 9) {
        $base_score += 35;
        $factors[] = ['factor' => 'HbA1c > 9%', 'weight' => 35, 'detail' => "سكر تراكمي {$hba1c}% — خطر مضاعفات مرتفع جداً"];
    } elseif ($hba1c > 7) {
        $base_score += 20;
        $factors[] = ['factor' => 'HbA1c مرتفع', 'weight' => 20, 'detail' => "سكر تراكمي {$hba1c}% — غير مضبوط"];
    }

    // Wagner grade progression
    $wagner = $data['wagner_grade'] ?? 0;
    if ($wagner >= 4) {
        $base_score += 40;
        $factors[] = ['factor' => 'Wagner 4-5', 'weight' => 40, 'detail' => 'درجة Wagner متقدمة — خطر بتر مرتفع جداً'];
    } elseif ($wagner >= 3) {
        $base_score += 30;
        $factors[] = ['factor' => 'Wagner 3', 'weight' => 30, 'detail' => 'درجة Wagner 3 — قرحة عميقة تحتاج تدخل'];
    } elseif ($wagner >= 2) {
        $base_score += 15;
        $factors[] = ['factor' => 'Wagner 2', 'weight' => 15, 'detail' => 'قرحة سطحية — تحتاج مراقبة'];
    }

    // Loss of sensation
    if (($data['right_sensation'] ?? '') === 'معدوم' || ($data['left_sensation'] ?? '') === 'معدوم') {
        $base_score += 20;
        $factors[] = ['factor' => 'فقدان الإحساس', 'weight' => 20, 'detail' => 'خطر الإصابة بالقرحة دون شعور'];
    }

    // No pulse
    if (($data['right_pulse'] ?? '') === 'معدوم' || ($data['left_pulse'] ?? '') === 'معدوم') {
        $base_score += 25;
        $factors[] = ['factor' => 'انعدام النبض', 'weight' => 25, 'detail' => 'نقص تروية طرفي — خطر البتر'];
    }

    // Smoking
    if (($data['smoking_status'] ?? '') === 'مدخن') {
        $base_score += 20;
        $factors[] = ['factor' => 'تدخين', 'weight' => 20, 'detail' => 'يزيد من خطر البتر ومضاعفات الأوعية'];
    }

    // Infected wound
    if (in_array($data['wound_condition'] ?? '', ['متسخة', 'صديد'])) {
        $base_score += 25;
        $factors[] = ['factor' => 'جرح ملتهب', 'weight' => 25, 'detail' => 'عدوى نشطة — تحتاج مضادات حيوية فورية'];
    }

    // Poor improvement
    if (isset($data['improvement_percentage']) && $data['improvement_percentage'] < 25) {
        $base_score += 15;
        $factors[] = ['factor' => 'ضعف الاستجابة', 'weight' => 15, 'detail' => 'نسبة التحسن أقل من 25%'];
    }

    // === HbA1c TREND (last 3 readings) ===
    $trend_stmt = $mysqli->prepare("SELECT v.visit_date, bs.hba1c_value 
        FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id 
        WHERE v.patient_id = ? AND bs.hba1c_value IS NOT NULL 
        ORDER BY v.visit_date DESC LIMIT 3");
    $trend_stmt->bind_param('i', $patient_id);
    $trend_stmt->execute();
    $hba1c_readings = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (count($hba1c_readings) >= 2) {
        $recent = $hba1c_readings[0]['hba1c_value'];
        $older = $hba1c_readings[count($hba1c_readings)-1]['hba1c_value'];
        $change = $recent - $older;
        if ($change > 1) {
            $base_score += 10;
            $trends[] = ['indicator' => 'اتجاه HbA1c', 'direction' => 'up', 'detail' => 'ارتفاع بمقدار ' . number_format($change, 1) . '%'];
        } elseif ($change < -1) {
            $trends[] = ['indicator' => 'اتجاه HbA1c', 'direction' => 'down', 'detail' => 'انخفاض بمقدار ' . number_format(abs($change), 1) . '%'];
        } else {
            $trends[] = ['indicator' => 'اتجاه HbA1c', 'direction' => 'stable', 'detail' => 'مستقر'];
        }
    }

    // === VISIT GAP ===
    $gap_stmt = $mysqli->query("SELECT DATEDIFF(CURDATE(), MAX(visit_date)) as gap_days FROM visits WHERE patient_id = $patient_id");
    $gap = $gap_stmt->fetch_assoc()['gap_days'];
    if ($gap > 60) {
        $base_score += 15;
        $trends[] = ['indicator' => 'انقطاع عن المتابعة', 'direction' => 'up', 'detail' => "آخر زيارة منذ {$gap} يوم"];
    } elseif ($gap > 30) {
        $base_score += 5;
        $trends[] = ['indicator' => 'تأخر في المتابعة', 'direction' => 'warning', 'detail' => "آخر زيارة منذ {$gap} يوم"];
    }

    // Classification
    if ($base_score >= 100) { $class = 'حرج جداً'; $color = '#7f1d1d'; }
    elseif ($base_score >= 70) { $class = 'مرتفع'; $color = '#dc2626'; }
    elseif ($base_score >= 40) { $class = 'متوسط'; $color = '#f59e0b'; }
    else { $class = 'منخفض'; $color = '#10b981'; }

    // === 30/60/90 DAY FORECAST ===
    $forecast_30d = '—';
    $forecast_60d = '—';
    $forecast_90d = '—';

    // Simple predictive logic based on score
    if ($base_score >= 80) {
        $forecast_30d = 'خطر مرتفع جداً — تدخل فوري مطلوب';
        $forecast_60d = 'خطر بتر مرتفع — متابعة أسبوعية';
        $forecast_90d = 'احتمال مضاعفات خطيرة — خطة علاج مكثف';
    } elseif ($base_score >= 50) {
        $forecast_30d = 'خطر متوسط — مراقبة';
        $forecast_60d = 'قد تتطور القرحة — متابعة كل أسبوعين';
        $forecast_90d = 'تحسن متوقع مع العلاج المناسب';
    } elseif ($base_score >= 25) {
        $forecast_30d = 'خطر منخفض — رعاية منتظمة';
        $forecast_60d = 'استقرار متوقع — متابعة شهرية';
        $forecast_90d = 'التئام متوقع مع الالتزام بالتعليمات';
    } else {
        $forecast_30d = 'حالة مستقرة — عناية يومية';
        $forecast_60d = 'متابعة روتينية';
        $forecast_90d = 'نتائج إيجابية متوقعة';
    }

    // Adjust forecast based on specific factors
    if ($wagner >= 3) {
        $forecast_30d = '🆘 خطر بتر — تدخل جراحي محتمل';
        $forecast_60d = 'مضاعفات خطيرة بدون تدخل فوري';
    }
    if ($gap > 90) {
        $forecast_30d = '⚠️ منقطع — خطر تدهور الحالة';
        $forecast_90d = 'مضاعفات متقدمة محتملة — يجب استدعاء المريض';
    }

    return [
        'score' => $base_score,
        'class' => $class,
        'color' => $color,
        'factors' => $factors,
        'trends' => $trends,
        'forecast_30d' => $forecast_30d,
        'forecast_60d' => $forecast_60d,
        'forecast_90d' => $forecast_90d,
        'wagner' => $wagner,
        'hba1c' => $hba1c,
        'gap_days' => $gap
    ];
}

// Get all active patients
$patients = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, p.age, p.gender,
    (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
    FROM patients p WHERE p.is_active = 1 ORDER BY p.created_at DESC");

$predictions = [];
while ($p = $patients->fetch_assoc()) {
    $p['prediction'] = predict_patient_risk($p['patient_id'], $mysqli);
    $predictions[] = $p;
}

// Sort by risk score (highest first)
usort($predictions, function($a, $b) {
    return $b['prediction']['score'] - $a['prediction']['score'];
});

// Aggregate statistics
$critical_count = 0; $high_count = 0; $medium_count = 0; $low_count = 0;
$avg_score = 0;
foreach ($predictions as $p) {
    $cls = $p['prediction']['class'];
    if ($cls === 'حرج جداً') $critical_count++;
    elseif ($cls === 'مرتفع') $high_count++;
    elseif ($cls === 'متوسط') $medium_count++;
    else $low_count++;
    $avg_score += $p['prediction']['score'];
}
$total = count($predictions);
$avg_score = $total > 0 ? round($avg_score / $total, 1) : 0;
?>
<style>
    .prediction-header {
        background: linear-gradient(135deg, #1a237e, #283593);
        color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;
        position: relative; overflow: hidden;
    }
    .prediction-header::before {
        content: '🔮'; position: absolute; left: -20px; top: -20px; font-size: 100px; opacity: 0.1;
    }
    .forecast-card {
        background: white; border-radius: 12px; padding: 1.2rem; text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s;
        border-top: 3px solid;
    }
    .forecast-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
    .forecast-card .days { font-size: 2rem; font-weight: 800; }
    .forecast-card .label { font-size: 0.85rem; color: #666; margin: 0.3rem 0; }
    .forecast-card .detail { font-size: 0.82rem; color: #444; line-height: 1.6; }

    .factor-list { display: flex; flex-direction: column; gap: 4px; }
    .factor-item {
        display: flex; align-items: center; gap: 8px;
        padding: 4px 8px; border-radius: 6px; font-size: 12px;
    }
    .factor-weight {
        display: inline-block; padding: 1px 8px; border-radius: 12px;
        font-weight: 700; font-size: 11px; color: white; min-width: 30px; text-align: center;
    }
    .trend-up { color: #dc2626; } .trend-down { color: #10b981; } .trend-stable { color: #f59e0b; }

    @media print { .no-print { display: none !important; } }
    @media (max-width: 768px) { .forecast-grid { grid-template-columns: 1fr; } }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="prediction-header fade-in">
    <h1>🔮 النموذج التنبؤي للمخاطر</h1>
    <p>Predictive Risk Model — 30/60/90 يوم توقعات مضاعفات القدم السكري</p>
    <div style="margin-top:1rem;display:flex;gap:1rem;flex-wrap:wrap;">
        <span style="background:rgba(255,255,255,0.15);padding:0.3rem 1rem;border-radius:20px;font-size:0.9rem;">
            إجمالي المرضى: <strong><?php echo $total; ?></strong>
        </span>
        <span style="background:rgba(255,255,255,0.15);padding:0.3rem 1rem;border-radius:20px;font-size:0.9rem;">
            متوسط درجة الخطورة: <strong><?php echo $avg_score; ?></strong>
        </span>
        <span style="background:rgba(255,255,255,0.15);padding:0.3rem 1rem;border-radius:20px;font-size:0.9rem;">
            حالات حرجة: <strong style="color:#ef9a9a;"><?php echo $critical_count; ?></strong>
        </span>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card card-fade-in" style="border-right:3px solid #7f1d1d;">
        <div class="stat-bar" style="background:linear-gradient(90deg,#7f1d1d,#dc2626);"></div>
        <div class="stat-value" style="color:#7f1d1d;"><?php echo $critical_count; ?></div>
        <div class="stat-label">🔴 حرج جداً</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#dc2626,#ef4444);"></div>
        <div class="stat-value" style="color:#dc2626;"><?php echo $high_count; ?></div>
        <div class="stat-label">🟠 مرتفع</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
        <div class="stat-value" style="color:#d97706;"><?php echo $medium_count; ?></div>
        <div class="stat-label">🟡 متوسط</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
        <div class="stat-value" style="color:#059669;"><?php echo $low_count; ?></div>
        <div class="stat-label">🟢 منخفض</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
        <div class="stat-value" style="color:#1d4ed8;font-size:28px;"><?php echo $avg_score; ?></div>
        <div class="stat-label">📊 متوسط الخطورة</div>
    </div>
</div>

<!-- Predictions Table -->
<div class="card">
    <div class="card-header"><div class="card-title">📋 قائمة التنبؤات حسب درجة الخطورة</div></div>
    <div class="table-container">
        <table>
            <thead><tr>
                <th>المريض</th>
                <th>الدرجة</th>
                <th>التصنيف</th>
                <th>آخر زيارة</th>
                <th>عوامل الخطر</th>
                <th>توقع 30 يوم</th>
                <th>توقع 60 يوم</th>
                <th>توقع 90 يوم</th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php if (empty($predictions)): ?>
                <tr><td colspan="9" style="color:#94a3b8;padding:40px;text-align:center;">لا توجد بيانات كافية للتنبؤ</td></tr>
                <?php else: ?>
                    <?php foreach ($predictions as $p): ?>
                    <?php $pred = $p['prediction']; ?>
                    <tr>
                        <td><strong><?php echo escape_output($p['full_name']); ?></strong><br><span style="font-size:11px;color:#94a3b8;">📁 <?php echo escape_output($p['file_number']); ?></span></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div style="position:relative;height:6px;background:#e2e8f0;border-radius:3px;width:60px;">
                                    <div style="position:absolute;left:0;top:0;height:100%;width:<?php echo min($pred['score'], 100); ?>%;background:<?php echo $pred['color']; ?>;border-radius:3px;"></div>
                                </div>
                                <strong style="color:<?php echo $pred['color']; ?>;"><?php echo $pred['score']; ?></strong>
                            </div>
                        </td>
                        <td>
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;background:<?php echo $pred['color']; ?>20;color:<?php echo $pred['color']; ?>;font-weight:700;font-size:12px;">
                                <?php echo $pred['class']; ?>
                            </span>
                        </td>
                        <td style="font-size:12px;"><?php echo $p['last_visit'] ?? '—'; ?></td>
                        <td style="max-width:200px;">
                            <?php if (!empty($pred['factors'])): ?>
                            <div class="factor-list">
                                <?php foreach (array_slice($pred['factors'], 0, 2) as $f): ?>
                                <div class="factor-item">
                                    <span class="factor-weight" style="background:<?php echo $f['weight'] >= 30 ? '#dc2626' : ($f['weight'] >= 20 ? '#f59e0b' : '#3b82f6'); ?>;">+<?php echo $f['weight']; ?></span>
                                    <span><?php echo escape_output($f['factor']); ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php if (count($pred['factors']) > 2): ?>
                                <span style="color:#94a3b8;font-size:11px;">+<?php echo count($pred['factors']) - 2; ?> عوامل أخرى</span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:11px;max-width:150px;"><?php echo escape_output($pred['forecast_30d']); ?></td>
                        <td style="font-size:11px;max-width:150px;"><?php echo escape_output($pred['forecast_60d']); ?></td>
                        <td style="font-size:11px;max-width:150px;"><?php echo escape_output($pred['forecast_90d']); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-secondary">عرض</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Notification Button -->
<div class="mt-4 text-center no-print">
    <a href="<?php echo BASE_URL; ?>/modules/analytics/notifications.php" class="btn btn-primary">
        🔔 عرض الإشعارات والتنبيهات
    </a>
    <a href="<?php echo BASE_URL; ?>/modules/analytics/risk_alerts.php" class="btn btn-secondary">
        ⚠️ تنبيهات الخطر
    </a>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>