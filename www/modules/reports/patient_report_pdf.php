<?php
/**
 * Patient Analytics Report — Professional PDF-printable report with full clinical data
 */
require_once __DIR__ . '/../../includes/auth_check.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patient_id) { header('Location: ' . BASE_URL . '/modules/patients/index.php'); exit; }

require_once __DIR__ . '/../../lib/pdf_generator.php';

$patient = $mysqli->query("SELECT p.*, mh.diabetes_type, mh.diagnosis_year, mh.duration_years, mh.smoking_status, 
    mh.physical_activity, mh.family_history, mh.other_chronic_diseases,
    c.has_retinopathy, c.has_nephropathy, c.has_neuropathy, c.has_cad, c.has_cva, c.has_pad
    FROM patients p 
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN complications c ON p.patient_id = c.patient_id
    WHERE p.patient_id = $patient_id")->fetch_assoc();

if (!$patient) { die('المريض غير موجود'); }

// Visit summary
$visits = $mysqli->query("SELECT v.*, vs.weight, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
    bs.hba1c_value, bs.fpg_value,
    fa.wagner_grade, fa.right_sensation, fa.left_sensation, fa.abpi_right, fa.abpi_left,
    fu.wound_size_cm2, fu.wound_condition, fu.wound_foot, fu.wound_depth, fu.initial_cause,
    o.improvement_percentage, o.healing_date, o.current_amputation,
    t.treatment_type,
    lr.ldl, lr.creatinine, lr.hdl, lr.triglycerides,
    u.full_name as doctor_name
    FROM visits v
    LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    LEFT JOIN treatments t ON v.visit_id = t.visit_id
    LEFT JOIN lab_results lr ON v.visit_id = lr.visit_id
    LEFT JOIN users u ON v.created_by = u.user_id
    WHERE v.patient_id = $patient_id
    ORDER BY v.visit_date DESC LIMIT 20");

// Aggregate stats
$stats = $mysqli->query("SELECT 
    COUNT(*) as total_visits,
    COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.visit_id END) as foot_exams,
    COUNT(DISTINCT CASE WHEN fu.ulcer_id IS NOT NULL THEN v.visit_id END) as wound_records,
    ROUND(AVG(bs.hba1c_value), 1) as avg_hba1c,
    ROUND(AVG(vs.bmi), 1) as avg_bmi,
    ROUND(AVG(vs.blood_pressure_systolic), 0) as avg_bp_sys,
    ROUND(AVG(vs.blood_pressure_diastolic), 0) as avg_bp_dia,
    MAX(o.improvement_percentage) as best_improvement,
    DATEDIFF(CURDATE(), MAX(v.visit_date)) as days_since_last,
    (SELECT initial_cause FROM foot_ulcers WHERE visit_id IN (SELECT visit_id FROM visits WHERE patient_id = $patient_id) GROUP BY initial_cause ORDER BY COUNT(*) DESC LIMIT 1) as common_cause
    FROM visits v
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE v.patient_id = $patient_id")->fetch_assoc();

// ===== Risk Prediction (inline — no HTTP API call) =====
$p = $patient;
$score = 0;
$factors = [];

// HbA1c
$hba1c_val = (float)($stats['avg_hba1c'] ?? 0);
if ($hba1c_val > 10)       { $score += 25; $factors[] = ['factor' => 'HbA1c > 10%', 'weight' => 25]; }
elseif ($hba1c_val > 8)    { $score += 15; $factors[] = ['factor' => 'HbA1c 8-10%', 'weight' => 15]; }
elseif ($hba1c_val > 7)    { $score += 8;  $factors[] = ['factor' => 'HbA1c 7-8%', 'weight' => 8]; }

// Wagner
$wagner = (int)$visits->fetch_assoc()['wagner_grade'] ?? 0;
$visits->data_seek(0); // Reset pointer
if ($wagner >= 4)       { $score += 25; $factors[] = ['factor' => 'Wagner 4-5', 'weight' => 25]; }
elseif ($wagner >= 3)   { $score += 18; $factors[] = ['factor' => 'Wagner 3', 'weight' => 18]; }
elseif ($wagner >= 2)   { $score += 10; $factors[] = ['factor' => 'Wagner 2', 'weight' => 10]; }

// Other factors
if (!empty($p['smoking_status']) && $p['smoking_status'] === 'مدخن')    { $score += 10; $factors[] = ['factor' => 'تدخين نشط', 'weight' => 10]; }
if (($stats['days_since_last'] ?? 0) > 60)                               { $score += 12; $factors[] = ['factor' => 'انقطاع عن المتابعة', 'weight' => 12]; }
if (($stats['avg_bmi'] ?? 0) > 30)                                       { $score += 6;  $factors[] = ['factor' => 'سمنة (BMI > 30)', 'weight' => 6]; }
if (!empty($p['other_chronic_diseases']))                                 { $score += 8;  $factors[] = ['factor' => 'أمراض مزمنة أخرى', 'weight' => 8]; }
if (($stats['avg_bp_sys'] ?? 0) > 140)                                   { $score += 6;  $factors[] = ['factor' => 'ارتفاع ضغط الدم', 'weight' => 6]; }

$score = min($score, 100);
if ($score >= 70)      $risk_class = 'عالية جداً';
elseif ($score >= 40)  $risk_class = 'عالية';
elseif ($score >= 20)  $risk_class = 'متوسطة';
else                   $risk_class = 'منخفضة';

$risk_data = [
    'score' => $score,
    'class' => $risk_class,
    'color' => $score >= 70 ? '#dc2626' : ($score >= 40 ? '#d97706' : '#10b981'),
    'probability' => round($score * 0.85, 1),
    'factors' => $factors
];

$patient_name_esc = htmlspecialchars($patient['full_name'], ENT_QUOTES, 'UTF-8');
$file_number_esc  = htmlspecialchars($patient['file_number'], ENT_QUOTES, 'UTF-8');
$city_esc         = htmlspecialchars($patient['city'] ?? '—', ENT_QUOTES, 'UTF-8');
$gender_esc       = htmlspecialchars($patient['gender'] ?? '—', ENT_QUOTES, 'UTF-8');
$diabetes_type_esc = htmlspecialchars($patient['diabetes_type'] ?? '—', ENT_QUOTES, 'UTF-8');
$smoking_esc      = htmlspecialchars($patient['smoking_status'] ?? '—', ENT_QUOTES, 'UTF-8');
$activity_esc     = htmlspecialchars($patient['physical_activity'] ?? '—', ENT_QUOTES, 'UTF-8');

$pdf = new ClinicPDF("تقرير المريض - {$patient_name_esc}");
$pdf->addPage();

// ===== HEADER =====
$pdf->writeHTML("<div class='header'>
    <h1>🏥 تقرير تحليلات المريض</h1>
    <p>Patient Analytics Report — مركز سري للغدد الصماء والسكري</p>
</div>");

// ===== PATIENT INFO =====
$pdf->writeHTML("<div class='section-title'>👤 بيانات المريض</div>");
$pdf->table(
    ['المعلومة', 'القيمة'],
    [
        ['الاسم', $patient_name_esc],
        ['رقم الملف', $file_number_esc],
        ['العمر', $patient['age'] ? $patient['age'] . ' سنة' : '—'],
        ['الجنس', $gender_esc],
        ['المدينة', $city_esc],
        ['نوع السكري', $diabetes_type_esc],
        ['مدة السكري', $patient['duration_years'] ? $patient['duration_years'] . ' سنة' : '—'],
        ['التدخين', $smoking_esc],
        ['النشاط البدني', $activity_esc],
        ['تاريخ التشخيص', $patient['diagnosis_year'] ?: '—'],
    ]
);

$pdf->spacer();

// ===== COMPLICATIONS =====
$complications = [];
foreach (['has_retinopathy' => 'اعتلال الشبكية', 'has_nephropathy' => 'اعتلال الكلى', 'has_neuropathy' => 'اعتلال الأعصاب', 'has_cad' => 'مرض الشريان التاجي', 'has_cva' => 'السكتة الدماغية', 'has_pad' => 'مرض الشرايين الطرفية'] as $key => $label) {
    if (!empty($patient[$key])) $complications[] = $label;
}
if (!empty($complications)) {
    $pdf->writeHTML("<div class='section-title'>⚠️ المضاعفات</div>");
    $pdf->write(implode(' • ', $complications), 10);
    $pdf->spacer();
}

// ===== KPI GRID =====
$avg_hba1c = $stats['avg_hba1c'] ?: '—';
$avg_bmi = $stats['avg_bmi'] ?: '—';
$days_since_color = ($stats['days_since_last'] ?? 0) > 60 ? '#dc2626' : '#059669';
$best_improvement = $stats['best_improvement'] ? $stats['best_improvement'] . '%' : '—';

$pdf->writeHTML("<div class='section-title'>📊 ملخص المؤشرات</div>");
$pdf->writeHTML("<div class='kpi-grid'>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#0a7e6e;'>{$stats['total_visits']}</div><div class='kpi-label'>إجمالي الزيارات</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#db2777;'>{$avg_hba1c}%</div><div class='kpi-label'>متوسط HbA1c</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#1d4ed8;'>{$avg_bmi}</div><div class='kpi-label'>متوسط BMI</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#d97706;'>{$stats['foot_exams']}</div><div class='kpi-label'>فحوصات القدم</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#059669;'>{$best_improvement}</div><div class='kpi-label'>أفضل تحسن</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:{$days_since_color};'>{$stats['days_since_last']} يوم</div><div class='kpi-label'>منذ آخر زيارة</div></div>");
$pdf->writeHTML("</div>");

// ===== RISK ASSESSMENT =====
$pdf->spacer();
$pdf->writeHTML("<div class='section-title'>🔮 تقييم المخاطر</div>");
$risk_color = $risk_data['color'];
$pdf->writeHTML("<div style='text-align:center;padding:10px;'>");
$score_val = $risk_data['score'];
$prob_val  = $risk_data['probability'];
$pdf->writeHTML("<div style='font-size:24pt;font-weight:bold;color:{$risk_color};'>{$score_val} نقطة</div>");
$pdf->writeHTML("<div style='font-size:10pt;color:#64748b;'>احتمال الخطورة: {$prob_val}%</div>");
$pdf->writeHTML("<div class='risk-meter' style='width:60%;margin:8px auto;'><div class='risk-fill' style='width:" . min($score_val, 100) . "%;background:{$risk_color};'></div></div>");
$badge_class = $score_val >= 70 ? 'danger' : ($score_val >= 40 ? 'warning' : 'success');
$risk_class_esc = htmlspecialchars($risk_data['class'], ENT_QUOTES, 'UTF-8');
$pdf->writeHTML("<span class='badge badge-{$badge_class}' style='font-size:10pt;padding:4px 16px;'>{$risk_class_esc}</span>");

if (!empty($risk_data['factors'])) {
    $pdf->spacer(8);
    $pdf->write("عوامل الخطر:", 10, true);
    foreach ($risk_data['factors'] as $f) {
        $factor_esc = htmlspecialchars($f['factor'], ENT_QUOTES, 'UTF-8');
        $pdf->write("• {$factor_esc} (وزن: +{$f['weight']})", 9);
    }
}
$pdf->writeHTML("</div>");

// ===== VISIT HISTORY TABLE =====
$pdf->spacer();
$pdf->writeHTML("<div class='section-title'>📈 سجل الزيارات</div>");
$visit_rows = [];
while ($v = $visits->fetch_assoc()) {
    $hba1c_display = $v['hba1c_value'] 
        ? "<span class='badge " . ($v['hba1c_value'] > 7 ? 'badge-danger' : 'badge-success') . "'>" . htmlspecialchars($v['hba1c_value'], ENT_QUOTES, 'UTF-8') . "%</span>" 
        : '—';
    $wagner_display = $v['wagner_grade'] !== null 
        ? "<span class='badge " . ($v['wagner_grade'] >= 3 ? 'badge-danger' : ($v['wagner_grade'] >= 2 ? 'badge-warning' : 'badge-success')) . "'>W" . htmlspecialchars($v['wagner_grade'], ENT_QUOTES, 'UTF-8') . "</span>" 
        : '—';
    $visit_date_esc = htmlspecialchars($v['visit_date'] ?? '—', ENT_QUOTES, 'UTF-8');
    $reason_esc     = htmlspecialchars($v['visit_reason'] ?? 'متابعة', ENT_QUOTES, 'UTF-8');
    $weight_disp    = $v['weight'] ? htmlspecialchars($v['weight'], ENT_QUOTES, 'UTF-8') . ' كجم' : '—';
    $bmi_disp       = $v['bmi'] ? htmlspecialchars($v['bmi'], ENT_QUOTES, 'UTF-8') : '—';
    $bp_disp        = $v['blood_pressure_systolic'] ? htmlspecialchars($v['blood_pressure_systolic'], ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($v['blood_pressure_diastolic'], ENT_QUOTES, 'UTF-8') : '—';
    $improve_disp   = $v['improvement_percentage'] !== null ? htmlspecialchars($v['improvement_percentage'], ENT_QUOTES, 'UTF-8') . '%' : '—';
    $visit_rows[] = [
        $visit_date_esc,
        $reason_esc,
        $hba1c_display,
        $wagner_display,
        $weight_disp,
        $bmi_disp,
        $bp_disp,
        $improve_disp
    ];
}
$pdf->table(
    ['التاريخ', 'السبب', 'HbA1c', 'Wagner', 'الوزن', 'BMI', 'الضغط', 'التحسن'],
    $visit_rows
);

// ===== LABS SUMMARY =====
$pdf->spacer();
$pdf->writeHTML("<div class='section-title'>🧪 تحاليل المختبر (آخر قراءة)</div>");
$latest_labs = $mysqli->query("SELECT lr.*, v.visit_date FROM lab_results lr JOIN visits v ON lr.visit_id = v.visit_id WHERE v.patient_id = $patient_id ORDER BY v.visit_date DESC LIMIT 1")->fetch_assoc();
if ($latest_labs) {
    $pdf->table(
        ['التحليل', 'القيمة'],
        [
            ['الكوليسترول الكلي', ($latest_labs['total_cholesterol'] ?? '—') . (isset($latest_labs['total_cholesterol']) ? ' mg/dL' : '')],
            ['LDL', ($latest_labs['ldl'] ?? '—') . (isset($latest_labs['ldl']) ? ' mg/dL' : '')],
            ['HDL', ($latest_labs['hdl'] ?? '—') . (isset($latest_labs['hdl']) ? ' mg/dL' : '')],
            ['الدهون الثلاثية', ($latest_labs['triglycerides'] ?? '—') . (isset($latest_labs['triglycerides']) ? ' mg/dL' : '')],
            ['الكرياتينين', ($latest_labs['creatinine'] ?? '—') . (isset($latest_labs['creatinine']) ? ' mg/dL' : '')],
            ['تاريخ التحليل', htmlspecialchars($latest_labs['test_date'] ?? '—', ENT_QUOTES, 'UTF-8')],
        ]
    );
}

// ===== TREATMENT SUMMARY =====
$pdf->spacer();
$pdf->writeHTML("<div class='section-title'>💊 العلاج الحالي</div>");
$latest_treatment = $mysqli->query("SELECT t.treatment_type, t.oral_meds_details, t.insulin_details, t.other_meds, v.visit_date
    FROM treatments t JOIN visits v ON t.visit_id = v.visit_id WHERE v.patient_id = $patient_id ORDER BY v.visit_date DESC LIMIT 1")->fetch_assoc();
if ($latest_treatment) {
    $rx_date_esc = htmlspecialchars($latest_treatment['visit_date'] ?? '—', ENT_QUOTES, 'UTF-8');
    $rx_type_esc = htmlspecialchars($latest_treatment['treatment_type'] ?? '—', ENT_QUOTES, 'UTF-8');
    $oral_esc    = htmlspecialchars($latest_treatment['oral_meds_details'] ?? '', ENT_QUOTES, 'UTF-8');
    $insulin_esc = htmlspecialchars($latest_treatment['insulin_details'] ?? '', ENT_QUOTES, 'UTF-8');
    $pdf->write("آخر وصفة علاجية: {$rx_date_esc}", 10, true);
    $pdf->write("نوع العلاج: {$rx_type_esc}", 10);
    if ($oral_esc)    $pdf->write("الأدوية الفموية: {$oral_esc}", 9);
    if ($insulin_esc) $pdf->write("الأنسولين: {$insulin_esc}", 9);
} else {
    $pdf->write("لا توجد معلومات علاجية مسجلة", 10);
}

// ===== CLINICAL RECOMMENDATIONS =====
$pdf->spacer();
$pdf->writeHTML("<div class='section-title'>💡 توصيات سريرية</div>");
$recs = [];
if (($stats['avg_hba1c'] ?? 0) > 7)        $recs[] = "📈 ارتفاع متوسط HbA1c ({$stats['avg_hba1c']}%) — مراجعة خطة العلاج";
if (($stats['avg_bp_sys'] ?? 0) > 140)      $recs[] = "💓 ارتفاع ضغط الدم — يحتاج مراقبة وعلاج";
if (($stats['avg_bmi'] ?? 0) > 30)          $recs[] = "⚖️ سمنة (BMI {$stats['avg_bmi']}) — برنامج غذائي وتمارين";
if (($stats['days_since_last'] ?? 0) > 60)  $recs[] = "⚠️ انقطاع عن المتابعة منذ {$stats['days_since_last']} يوم — استدعاء المريض";
if ($patient['smoking_status'] === 'مدخن')  $recs[] = "🚬 تدخين نشط — برنامج إقلاع عن التدخين";
if (($stats['wound_records'] ?? 0) > 0 && ($stats['best_improvement'] ?? 0) < 100) $recs[] = "🩹 جروح نشطة — متابعة مكثفة للعناية بالجروح";

if (empty($recs)) $recs[] = "✅ لا توجد توصيات عاجلة — متابعة روتينية";

foreach ($recs as $r) {
    $pdf->write($r, 10);
}

$pdf->render("patient_report_{$file_number_esc}.pdf");
?>