<?php
/**
 * Clinic Analytics Monthly Report — Professional PDF-printable analytics summary
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../lib/pdf_generator.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// ===== KEY KPIs =====
$kpi = [];
$kpi['total_patients'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
$kpi['new_patients'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE created_at BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];
$kpi['total_visits'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];
$kpi['avg_hba1c'] = $mysqli->query("SELECT ROUND(AVG(bs.hba1c_value), 1) as avg FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['avg'];
$kpi['healing_rate'] = $mysqli->query("SELECT ROUND(COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate FROM visits v LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id LEFT JOIN outcomes o ON v.visit_id = o.visit_id WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['rate'];
$kpi['high_risk'] = (int)$mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3 AND v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];
$kpi['amputations'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE current_amputation_date BETWEEN '$from_date' AND '$to_date' AND current_amputation IS NOT NULL AND current_amputation != 'لا'")->fetch_assoc()['c'];
$kpi['deaths'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE death_date BETWEEN '$from_date' AND '$to_date' AND is_deceased = 1")->fetch_assoc()['c'];
$kpi['dropout'] = $mysqli->query("SELECT ROUND(COUNT(DISTINCT CASE WHEN (SELECT MAX(v2.visit_date) FROM visits v2 WHERE v2.patient_id = p.patient_id) < DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND p.is_active = 1 THEN p.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN p.is_active = 1 THEN p.patient_id END), 0) * 100, 1) as rate FROM patients p")->fetch_assoc()['rate'];

// ===== DEMOGRAPHICS =====
$gender_dist = [
    'ذكر' => (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'ذكر' AND is_active = 1")->fetch_assoc()['c'],
    'أنثى' => (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'أنثى' AND is_active = 1")->fetch_assoc()['c']
];

$age_groups = [];
$result = $mysqli->query("SELECT CASE WHEN age < 18 THEN '<18' WHEN age BETWEEN 18 AND 30 THEN '18-30' WHEN age BETWEEN 31 AND 45 THEN '31-45' WHEN age BETWEEN 46 AND 60 THEN '46-60' WHEN age BETWEEN 61 AND 75 THEN '61-75' ELSE '75+' END as grp, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY grp");
while ($row = $result->fetch_assoc()) $age_groups[] = $row;

// ===== MONTHLY TREND =====
$monthly = [];
$result = $mysqli->query("SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as cnt FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date' GROUP BY DATE_FORMAT(visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) $monthly[] = $row;

// ===== WAGNER DISTRIBUTION =====
$wagner = [];
$result = $mysqli->query("SELECT COALESCE(wagner_grade, -1) as grade, COUNT(*) as cnt FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' GROUP BY wagner_grade ORDER BY grade");
while ($row = $result->fetch_assoc()) $wagner[] = $row;

// ===== TOP CAUSES =====
$causes = [];
$result = $mysqli->query("SELECT fu.initial_cause, COUNT(*) as cnt FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fu.initial_cause IS NOT NULL GROUP BY fu.initial_cause ORDER BY cnt DESC LIMIT 5");
while ($row = $result->fetch_assoc()) $causes[] = $row;

// ===== DOCTOR PERFORMANCE =====
$doctors = [];
$result = $mysqli->query("SELECT u.full_name, COUNT(DISTINCT v.visit_id) as visits, COUNT(DISTINCT v.patient_id) as patients, COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.visit_id END) as foot_exams FROM users u LEFT JOIN visits v ON u.user_id = v.created_by AND v.visit_date BETWEEN '$from_date' AND '$to_date' LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE u.is_active = 1 AND u.role IN ('doctor', 'admin') GROUP BY u.user_id HAVING visits > 0 ORDER BY visits DESC");
while ($row = $result->fetch_assoc()) $doctors[] = $row;

// ===== OUTCOMES =====
$outcomes = [];
$result = $mysqli->query("SELECT 
    COUNT(CASE WHEN o.improvement_percentage >= 100 THEN 1 END) as healed,
    COUNT(CASE WHEN o.improvement_percentage >= 75 AND o.improvement_percentage < 100 THEN 1 END) as significant,
    COUNT(CASE WHEN o.improvement_percentage >= 50 AND o.improvement_percentage < 75 THEN 1 END) as moderate,
    COUNT(CASE WHEN o.improvement_percentage >= 25 AND o.improvement_percentage < 50 THEN 1 END) as mild,
    COUNT(CASE WHEN o.improvement_percentage < 25 AND o.improvement_percentage IS NOT NULL THEN 1 END) as poor,
    COUNT(*) as total
    FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// ===== GENERATE REPORT =====
$pdf = new ClinicPDF("التقرير الشهري - العيادة");
$pdf->addPage();

// HEADER
$pdf->writeHTML("<div class='header'>
    <h1>🏥 تقرير تحليلات العيادة الشهري</h1>
    <p>Clinic Analytics Monthly Report — " . date('Y-m-d') . "</p>
    <p style='color:#64748b;font-size:9pt;'>الفترة: {$from_date} إلى {$to_date}</p>
</div>");

// KPI GRID
$dropout_val = $kpi['dropout'] ?: '0';
$dropout_color = ((float)$dropout_val) > 30 ? '#dc2626' : (((float)$dropout_val) > 15 ? '#d97706' : '#059669');

$pdf->writeHTML("<div class='kpi-grid'>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#0a7e6e;'>{$kpi['total_patients']}</div><div class='kpi-label'>إجمالي المرضى</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#3b82f6;'>{$kpi['new_patients']}</div><div class='kpi-label'>مرضى جدد</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#1d4ed8;'>{$kpi['total_visits']}</div><div class='kpi-label'>الزيارات</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#db2777;'>" . ($kpi['avg_hba1c'] ?: '—') . "%</div><div class='kpi-label'>متوسط HbA1c</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#059669;'>" . ($kpi['healing_rate'] ?: '—') . "%</div><div class='kpi-label'>معدل الشفاء</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#dc2626;'>{$kpi['high_risk']}</div><div class='kpi-label'>حالات حرجة</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:#7f1d1d;'>{$kpi['amputations']}</div><div class='kpi-label'>حالات بتر</div></div>");
$pdf->writeHTML("<div class='kpi-item'><div class='kpi-value' style='color:{$dropout_color};'>{$dropout_val}%</div><div class='kpi-label'>تسرب</div></div>");
$pdf->writeHTML("</div>");

$pdf->spacer();

// DEMOGRAPHICS
$pdf->writeHTML("<div class='section-title'>👤 التوزيع الديموغرافي</div>");
$total_gender = $gender_dist['ذكر'] + $gender_dist['أنثى'];
$male_pct = $total_gender > 0 ? round($gender_dist['ذكر'] / $total_gender * 100, 1) : 0;
$female_pct = $total_gender > 0 ? round($gender_dist['أنثى'] / $total_gender * 100, 1) : 0;
$pdf->write("الجنس: ذكر {$gender_dist['ذكر']} ({$male_pct}%) | أنثى {$gender_dist['أنثى']} ({$female_pct}%)", 10);
$age_str = '';
foreach ($age_groups as $ag) $age_str .= htmlspecialchars($ag['grp'], ENT_QUOTES, 'UTF-8') . ": {$ag['cnt']} | ";
$pdf->write("الفئات العمرية: " . rtrim($age_str, ' | '), 10);

$pdf->spacer();

// MONTHLY TREND
$pdf->writeHTML("<div class='section-title'>📈 الاتجاه الشهري للزيارات</div>");
$month_rows = [];
foreach ($monthly as $m) $month_rows[] = [htmlspecialchars($m['month'], ENT_QUOTES, 'UTF-8'), $m['cnt']];
$pdf->table(['الشهر', 'عدد الزيارات'], $month_rows);

$pdf->spacer();

// WAGNER DISTRIBUTION
$pdf->writeHTML("<div class='section-title'>⚠️ توزيع درجات Wagner</div>");
$wagner_rows = [];
$wagner_total = array_sum(array_column($wagner, 'cnt'));
foreach ($wagner as $w) {
    $pct = $wagner_total > 0 ? round($w['cnt'] / $wagner_total * 100, 1) : 0;
    $grade_label = $w['grade'] == -1 ? 'غير محدد' : 'Wagner ' . $w['grade'];
    $wagner_rows[] = [$grade_label, $w['cnt'], $pct . '%'];
}
$pdf->table(['الدرجة', 'العدد', 'النسبة'], $wagner_rows);

$pdf->spacer();

// CAUSES
if (!empty($causes)) {
    $pdf->writeHTML("<div class='section-title'>🩹 أسباب الجروح الأكثر شيوعاً</div>");
    $cause_rows = [];
    foreach ($causes as $c) $cause_rows[] = [htmlspecialchars($c['initial_cause'], ENT_QUOTES, 'UTF-8'), $c['cnt']];
    $pdf->table(['السبب', 'العدد'], $cause_rows);
    $pdf->spacer();
}

// OUTCOMES
$pdf->writeHTML("<div class='section-title'>📊 نتائج العلاج</div>");
$outcome_rows = [];
if ($outcomes['total'] > 0) {
    foreach ([
        ['التئام كامل (100%)', (int)$outcomes['healed']],
        ['تحسن كبير (75-99%)', (int)$outcomes['significant']],
        ['تحسن متوسط (50-74%)', (int)$outcomes['moderate']],
        ['تحسن طفيف (25-49%)', (int)$outcomes['mild']],
        ['تحسن ضعيف (<25%)', (int)$outcomes['poor']],
    ] as $o) {
        $pct = round($o[1] / $outcomes['total'] * 100, 1);
        $outcome_rows[] = [$o[0], $o[1], $pct . '%'];
    }
}
$pdf->table(['النتيجة', 'العدد', 'النسبة'], $outcome_rows);

$pdf->spacer();

// DOCTOR PERFORMANCE
if (!empty($doctors)) {
    $pdf->writeHTML("<div class='section-title'>👨‍⚕️ أداء الأطباء</div>");
    $doc_rows = [];
    foreach ($doctors as $d) $doc_rows[] = [
        htmlspecialchars($d['full_name'], ENT_QUOTES, 'UTF-8'),
        $d['visits'],
        $d['patients'],
        $d['foot_exams']
    ];
    $pdf->table(['الطبيب', 'الزيارات', 'المرضى', 'فحوصات القدم'], $doc_rows);
}

$pdf->spacer();

// KEY INSIGHTS
$pdf->writeHTML("<div class='section-title'>💡 الرؤى والتوصيات</div>");
$insights = [];
if ($kpi['healing_rate'] && $kpi['healing_rate'] < 60) $insights[] = "⚠️ معدل الشفاء {$kpi['healing_rate']}% — أقل من المستوى المستهدف (70%). مراجعة بروتوكولات العلاج.";
if ($kpi['avg_hba1c'] && $kpi['avg_hba1c'] > 7.5) $insights[] = "📈 متوسط HbA1c ({$kpi['avg_hba1c']}%) أعلى من المستهدف. تكثيف خطط السيطرة على السكري.";
if ($kpi['dropout'] && $kpi['dropout'] > 30) $insights[] = "🚪 نسبة التسرب {$kpi['dropout']}% — برنامج استدعاء للمرضى المنقطعين.";
if ($kpi['amputations'] > 0) $insights[] = "🦶 {$kpi['amputations']} حالة بتر — مراجعة بروتوكول العناية بالقدم.";
if ($kpi['high_risk'] > 5) $insights[] = "🔴 {$kpi['high_risk']} حالة حرجة (Wagner 3+) — تخصيص جلسات متابعة أسبوعية.";
if ($gender_dist['ذكر'] > $gender_dist['أنثى']) {
    $diff = $male_pct - $female_pct;
    $insights[] = "👤 نسبة الذكور ({$male_pct}%) أعلى من الإناث ({$female_pct}%) — النظر في برامج توعية خاصة بالإناث.";
}
if (empty($insights)) $insights[] = "✅ المؤشرات ضمن المستويات المقبولة. استمرار المتابعة الروتينية.";

foreach ($insights as $insight) {
    $pdf->write($insight, 10);
}

$pdf->render("clinic_report_{$from_date}_to_{$to_date}.pdf");
?>