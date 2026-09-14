<?php
/**
 * Medical Specialist Analytics
 * Professional-grade analytics for endocrinologists, diabetologists & podiatrists
 */
$page_title = '🔬 تحليلات متخصصة | Specialist Analytics';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Date range filter
$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));
$active_tab = $_GET['tab'] ?? 'quality';
$doctor_filter = (int)($_GET['doctor_id'] ?? 0);

// ============================================================
// SECTION 1: CLINICAL QUALITY METRICS
// ============================================================

// 1a. HbA1c Control Rate (% of patients with latest HbA1c < 7%)
$hba1c_controlled = $mysqli->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN bs.hba1c_value < 7 THEN v.patient_id END) as controlled,
        COUNT(DISTINCT v.patient_id) as total,
        ROUND(COUNT(DISTINCT CASE WHEN bs.hba1c_value < 7 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT v.patient_id), 0) * 100, 1) as rate
    FROM visits v 
    JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// 1b. Foot Exam Completion Rate
$foot_exam_rate = $mysqli->query("
    SELECT 
        COUNT(DISTINCT v.patient_id) as total_visits,
        COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END) as with_exam,
        ROUND(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END) / NULLIF(COUNT(DISTINCT v.patient_id), 0) * 100, 1) as rate
    FROM visits v
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// 1c. BP Control Rate (systolic < 130 AND diastolic < 80)
$bp_control = $mysqli->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic < 130 AND vs.blood_pressure_diastolic < 80 THEN v.patient_id END) as controlled,
        COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic IS NOT NULL THEN v.patient_id END) as total,
        ROUND(COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic < 130 AND vs.blood_pressure_diastolic < 80 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate
    FROM visits v
    JOIN vital_signs vs ON v.visit_id = vs.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// 1d. LDL Control Rate (< 100 mg/dL)
$ldl_control = $mysqli->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN lr.ldl < 100 THEN v.patient_id END) as controlled,
        COUNT(DISTINCT CASE WHEN lr.ldl IS NOT NULL THEN v.patient_id END) as total,
        ROUND(COUNT(DISTINCT CASE WHEN lr.ldl < 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN lr.ldl IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate
    FROM visits v
    JOIN lab_results lr ON v.visit_id = lr.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// 1e. Smoking Rate
$smoking_rate = $mysqli->query("
    SELECT 
        COUNT(CASE WHEN mh.smoking_status = 'مدخن' THEN 1 END) as smokers,
        COUNT(*) as total,
        ROUND(COUNT(CASE WHEN mh.smoking_status = 'مدخن' THEN 1 END) / NULLIF(COUNT(*), 0) * 100, 1) as rate
    FROM medical_history mh
")->fetch_assoc();

// 1f. Healing Rate (patients who reached 100% improvement)
$healing_rate = $mysqli->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) as healed,
        COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL OR fu.ulcer_id IS NOT NULL THEN v.patient_id END) as with_wounds,
        ROUND(COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL OR fu.ulcer_id IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate
    FROM visits v
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc();

// 1g. Amputation Prevention Rate
$amputation_data = $mysqli->query("
    SELECT 
        COUNT(DISTINCT v.patient_id) as total_wagner3,
        COUNT(DISTINCT CASE WHEN o.current_amputation IS NOT NULL AND o.current_amputation != 'لا' THEN v.patient_id END) as amputated
    FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fa.wagner_grade >= 3
")->fetch_assoc();
$prevented_amp = $amputation_data['total_wagner3'] - $amputation_data['amputated'];

// ============================================================
// SECTION 2: POPULATION HEALTH
// ============================================================

// 2a. Age pyramid
$age_pyramid = [];
$result = $mysqli->query("
    SELECT 
        CASE 
            WHEN age < 18 THEN '0-17'
            WHEN age BETWEEN 18 AND 30 THEN '18-30'
            WHEN age BETWEEN 31 AND 45 THEN '31-45'
            WHEN age BETWEEN 46 AND 60 THEN '46-60'
            WHEN age BETWEEN 61 AND 75 THEN '61-75'
            ELSE '75+'
        END as age_group,
        gender,
        COUNT(*) as cnt
    FROM patients WHERE is_active = 1
    GROUP BY age_group, gender
    ORDER BY MIN(age)
");
while ($row = $result->fetch_assoc()) {
    $age_pyramid[] = $row;
}

// 2b. Comorbidity burden
$comorbidity_data = $mysqli->query("
    SELECT 
        ROUND(AVG(has_retinopathy + has_nephropathy + has_neuropathy + has_cad + has_cva + has_pad), 1) as avg_comorbidities,
        COUNT(*) as total
    FROM complications
")->fetch_assoc();

$comorbidity_dist = [];
$result = $mysqli->query("
    SELECT 
        (has_retinopathy + has_nephropathy + has_neuropathy + has_cad + has_cva + has_pad) as comorbidity_count,
        COUNT(*) as cnt
    FROM complications
    GROUP BY comorbidity_count
    ORDER BY comorbidity_count
");
while ($row = $result->fetch_assoc()) {
    $comorbidity_dist[] = $row;
}

// 2c. Geographic distribution with distance
$geo_data = [];
$result = $mysqli->query("
    SELECT city, COUNT(*) as cnt, ROUND(AVG(distance_from_center), 1) as avg_distance
    FROM patients WHERE is_active = 1 AND city IS NOT NULL AND city != ''
    GROUP BY city ORDER BY cnt DESC LIMIT 8
");
while ($row = $result->fetch_assoc()) {
    $geo_data[] = $row;
}

// 2d. Treatment modality distribution
$treatment_modality = [];
$result = $mysqli->query("
    SELECT treatment_type, COUNT(*) as cnt
    FROM treatments WHERE treatment_type IS NOT NULL AND treatment_type != ''
    GROUP BY treatment_type ORDER BY cnt DESC
");
while ($row = $result->fetch_assoc()) {
    $treatment_modality[] = $row;
}

// ============================================================
// SECTION 3: OUTCOME ANALYSIS
// ============================================================

// 3a. Healing success by Wagner grade
$wagner_healing = [];
$result = $mysqli->query("
    SELECT 
        fa.wagner_grade,
        COUNT(DISTINCT v.patient_id) as total,
        COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) as healed,
        ROUND(AVG(o.improvement_percentage), 1) as avg_improvement,
        ROUND(AVG(DATEDIFF(o.healing_date, v.visit_date)), 0) as avg_days
    FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fa.wagner_grade IS NOT NULL
    GROUP BY fa.wagner_grade
    ORDER BY fa.wagner_grade
");
while ($row = $result->fetch_assoc()) {
    $wagner_healing[] = $row;
}

// 3b. Wound cause analysis
$wound_causes = [];
$result = $mysqli->query("
    SELECT initial_cause, COUNT(*) as cnt, 
           ROUND(AVG(o.improvement_percentage), 1) as avg_improvement
    FROM foot_ulcers fu
    LEFT JOIN outcomes o ON fu.visit_id = o.visit_id
    WHERE initial_cause IS NOT NULL AND initial_cause != ''
    GROUP BY initial_cause ORDER BY cnt DESC
");
while ($row = $result->fetch_assoc()) {
    $wound_causes[] = $row;
}

// 3c. Amputation types
$amputation_types = [];
$result = $mysqli->query("
    SELECT current_amputation, COUNT(*) as cnt
    FROM outcomes 
    WHERE current_amputation IS NOT NULL AND current_amputation != 'لا'
    GROUP BY current_amputation ORDER BY cnt DESC
");
while ($row = $result->fetch_assoc()) {
    $amputation_types[] = $row;
}

// ============================================================
// SECTION 4: PROVIDER PERFORMANCE
// ============================================================

$doctor_where = $doctor_filter > 0 ? "AND v.created_by = $doctor_filter" : "";

$doctor_performance = [];
$result = $mysqli->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.role,
        COUNT(DISTINCT v.visit_id) as total_visits,
        COUNT(DISTINCT v.patient_id) as unique_patients,
        COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.visit_id END) as foot_exams,
        COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) as healed,
        ROUND(AVG(o.improvement_percentage), 1) as avg_improvement
    FROM users u
    LEFT JOIN visits v ON u.user_id = v.created_by AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE u.is_active = 1 AND u.role IN ('doctor', 'admin')
    GROUP BY u.user_id
    HAVING total_visits > 0
    ORDER BY total_visits DESC
");
while ($row = $result->fetch_assoc()) {
    $doctor_performance[] = $row;
}

// ============================================================
// SECTION 5: DECISION SUPPORT INSIGHTS
// ============================================================

// 5a. High-risk clusters
$high_risk_patients = $mysqli->query("
    SELECT p.patient_id, p.full_name, p.file_number, p.phone_primary,
           mh.smoking_status, mh.diabetes_type,
           fa.wagner_grade, fa.right_sensation, fa.left_sensation,
           bs.hba1c_value,
           o.improvement_percentage,
           (SELECT MAX(v2.visit_date) FROM visits v2 WHERE v2.patient_id = p.patient_id) as last_visit
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.is_active = 1 AND (fa.wagner_grade >= 3 OR (o.improvement_percentage IS NULL OR o.improvement_percentage < 50))
    GROUP BY p.patient_id
    ORDER BY fa.wagner_grade DESC, o.improvement_percentage ASC
    LIMIT 10
");

// 5b. Missed follow-up (>90 days)
$missed_count = $mysqli->query("
    SELECT COUNT(*) as cnt FROM patients p
    WHERE p.is_active = 1 AND (
        SELECT MAX(v.visit_date) FROM visits v WHERE v.patient_id = p.patient_id
    ) < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
")->fetch_assoc()['cnt'];

// 5c. Actionable insights
$actionable = [];

// Insight: High HbA1c trend
$hba1c_up = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v
    JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value > 9 AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];
if ($hba1c_up > 0) {
    $actionable[] = ['icon' => '🩸', 'title' => "$hba1c_up مريض بـ HbA1c > 9%", 'desc' => 'يحتاجون تدخل علاجي عاجل', 'type' => 'critical'];
}

// Insight: Smoking + Foot ulcer
$smoking_ulcer = $mysqli->query("
    SELECT COUNT(DISTINCT p.patient_id) as cnt FROM patients p
    JOIN medical_history mh ON p.patient_id = mh.patient_id
    JOIN visits v ON p.patient_id = v.patient_id
    JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    WHERE mh.smoking_status = 'مدخن' AND p.is_active = 1
")->fetch_assoc()['cnt'];
if ($smoking_ulcer > 0) {
    $actionable[] = ['icon' => '🚬', 'title' => "$smoking_ulcer مريض مدخن مع جروح قدم", 'desc' => 'خطر بتر مرتفع — برنامج إقلاع عن التدخين', 'type' => 'warning'];
}

// Insight: No foot exam
$no_foot_exam = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
    AND NOT EXISTS (SELECT 1 FROM foot_assessments fa WHERE fa.visit_id = v.visit_id)
")->fetch_assoc()['cnt'];
if ($no_foot_exam > 0) {
    $actionable[] = ['icon' => '🦶', 'title' => "$no_foot_exam زيارة بدون فحص قدم", 'desc' => 'نسبة إتمام فحص القدم منخفضة', 'type' => 'info'];
}

// Insight: Sensory loss prevalence
$sensory_loss = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    WHERE (fa.right_sensation = 'معدوم' OR fa.left_sensation = 'معدوم')
")->fetch_assoc()['cnt'];
if ($sensory_loss > 0) {
    $actionable[] = ['icon' => '⚠️', 'title' => "$sensory_loss مريض بفقدان حس كامل", 'desc' => 'خطر قدم سكري مرتفع — أحذية وقائية', 'type' => 'critical'];
}

// ============================================================
// SECTION 6: TRENDS
// ============================================================

// 6a. Monthly new patients
$new_patients_trend = [];
$result = $mysqli->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt
    FROM patients
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
while ($row = $result->fetch_assoc()) {
    $new_patients_trend[] = $row;
}

// 6b. Monthly healing trend
$hba1c_trend = [];
$result = $mysqli->query("
    SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as month, AVG(bs.hba1c_value) as avg_hba1c
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m') ORDER BY month
");
while ($row = $result->fetch_assoc()) {
    $hba1c_trend[] = $row;
}

$healing_trend = [];
$result = $mysqli->query("
    SELECT DATE_FORMAT(o.healing_date, '%Y-%m') as month, COUNT(*) as cnt
    FROM outcomes o
    JOIN visits v ON o.visit_id = v.visit_id
    WHERE o.healing_date IS NOT NULL AND o.healing_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(o.healing_date, '%Y-%m')
    ORDER BY month
");
while ($row = $result->fetch_assoc()) {
    $healing_trend[] = $row;
}

// Get all doctors for filter
$doctors = $mysqli->query("SELECT user_id, full_name FROM users WHERE is_active = 1 AND role IN ('doctor', 'admin') ORDER BY full_name");
?>
<style>
    .spec-hero {
        background: linear-gradient(135deg, #0a2342 0%, #1a3a5c 50%, #0d2b4a 100%);
        color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;
        position: relative; overflow: hidden;
    }
    .spec-hero::before { content: '🔬'; position: absolute; left: -10px; top: -20px; font-size: 120px; opacity: 0.08; }
    .spec-hero h1 { font-size: 1.6rem; font-weight: 800; }
    .spec-hero p { opacity: 0.8; font-size: 0.9rem; }

    .quality-ring {
        width: 70px; height: 70px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; font-weight: 800; position: relative;
        margin: 0 auto 0.4rem;
    }
    .quality-card {
        text-align: center; padding: 1rem; background: var(--bg-card);
        border-radius: 12px; border: 1px solid var(--border);
        transition: var(--transition);
    }
    .quality-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    .quality-label { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; }
    .quality-target { font-size: 0.7rem; color: var(--text-light); }

    .insight-banner {
        padding: 0.8rem 1rem; border-radius: 10px; margin-bottom: 0.5rem;
        display: flex; align-items: center; gap: 0.8rem; font-size: 0.85rem;
        border-right: 4px solid;
    }
    .insight-critical { background: #fef2f2; border-color: #dc2626; }
    .insight-warning { background: #fffbeb; border-color: #f59e0b; }
    .insight-info { background: #eff6ff; border-color: #3b82f6; }
    .insight-success { background: #ecfdf5; border-color: #10b981; }

    .spec-stat {
        padding: 0.8rem; border-radius: 10px; border: 1px solid var(--border);
        text-align: center; transition: var(--transition);
    }
    .spec-stat:hover { background: var(--bg-table-stripe); }
    .spec-stat .value { font-size: 1.5rem; font-weight: 800; }
    .spec-stat .label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; }

    .tab-nav { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .tab-nav a { padding: 0.5rem 1.2rem; border-radius: 8px; font-size: 0.85rem; 
                 font-weight: 600; transition: var(--transition); text-decoration: none; }
    .tab-nav a.active { background: var(--teal); color: #fff; box-shadow: 0 4px 12px rgba(10,126,110,0.2); }
    .tab-nav a:not(.active) { background: var(--bg-input); color: var(--text-body); border: 1px solid var(--border); }
    .tab-nav a:not(.active):hover { background: var(--teal-pale); }

    .metric-bar { height: 6px; border-radius: 3px; background: var(--border); margin-top: 0.5rem; overflow: hidden; }
    .metric-bar-fill { height: 100%; border-radius: 3px; transition: width 0.8s ease; }

    .provider-card {
        display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--border); transition: var(--transition);
    }
    .provider-card:last-child { border-bottom: none; }
    .provider-card:hover { background: var(--bg-table-hover); }
    .provider-rank { font-size: 1.2rem; font-weight: 800; color: var(--text-light); width: 30px; text-align: center; }

    @media (max-width: 768px) {
        .spec-hero { padding: 1.2rem; }
        .spec-hero h1 { font-size: 1.2rem; }
    }

    /* Dark mode overrides for insight banners */
    [data-theme="dark"] .insight-critical { background: #2d0a0a; }
    [data-theme="dark"] .insight-warning { background: #2d2000; }
    [data-theme="dark"] .insight-info { background: #0a1a2d; }
    [data-theme="dark"] .insight-success { background: #0a1d10; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="spec-hero">
    <div class="flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1>🔬 تحليلات طبية متخصصة</h1>
            <p>Medical Specialist Analytics — جودة الرعاية، صحة السكان، فعالية العلاج</p>
        </div>
        <form method="get" class="flex flex-wrap gap-2 items-center">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
            <label style="font-size:12px;color:rgba(255,255,255,0.7);">من</label>
            <input type="date" name="from_date" value="<?php echo $from_date; ?>" style="padding:4px 8px;border-radius:6px;border:none;font-size:12px;font-family:'Tajawal',sans-serif;">
            <label style="font-size:12px;color:rgba(255,255,255,0.7);">إلى</label>
            <input type="date" name="to_date" value="<?php echo $to_date; ?>" style="padding:4px 8px;border-radius:6px;border:none;font-size:12px;font-family:'Tajawal',sans-serif;">
            <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);">تحديث</button>
        </form>
    </div>
</div>

<!-- Tab Navigation -->
<div class="tab-nav">
    <!-- QI Score Summary Banner -->
<div class="card mb-4" style="background:linear-gradient(135deg,#0a7e6e,#13a896);color:#fff;border:none;">
    <div class="flex justify-between items-center flex-wrap gap-3" style="padding:0.5rem 0;">
        <div style="display:flex;align-items:center;gap:1.5rem;">
            <?php
                // Calculate QI Score from quality metrics
                $qi_hba1c = min(($hba1c_controlled['rate'] ?? 0) / 50 * 100, 100); // weight 25%
                $qi_foot = min(($foot_exam_rate['rate'] ?? 0) / 80 * 100, 100);   // weight 20%
                $qi_bp = min(($bp_control['rate'] ?? 0) / 60 * 100, 100);         // weight 15%
                $qi_ldl = min(($ldl_control['rate'] ?? 0) / 50 * 100, 100);       // weight 15%
                $qi_smok = min((15 / max($smoking_rate['rate'] ?? 1, 1)) * 100, 100); // weight 10% (lower is better)
                $qi_heal = min(($healing_rate['rate'] ?? 0) / 70 * 100, 100);     // weight 15%
                
                $qi_score = round($qi_hba1c * 0.25 + $qi_foot * 0.20 + $qi_bp * 0.15 + $qi_ldl * 0.15 + $qi_smok * 0.10 + $qi_heal * 0.15, 1);
                
                $qi_grade = $qi_score >= 85 ? 'ممتاز' : ($qi_score >= 65 ? 'جيد' : ($qi_score >= 45 ? 'مقبول' : 'يحتاج تحسين'));
                $qi_color = $qi_score >= 85 ? '#10b981' : ($qi_score >= 65 ? '#f59e0b' : ($qi_score >= 45 ? '#3b82f6' : '#ef4444'));
                $qi_icon = $qi_score >= 85 ? '🏆' : ($qi_score >= 65 ? '👍' : ($qi_score >= 45 ? '📊' : '🔴'));
            ?>
            <div style="text-align:center;">
                <div style="font-size:2.5rem;font-weight:900;line-height:1;"><?php echo $qi_score; ?>%</div>
                <div style="font-size:11px;opacity:0.85;">QI Score</div>
            </div>
            <div>
                <div style="font-size:1.1rem;font-weight:700;"><?php echo $qi_icon; ?> <?php echo $qi_grade; ?></div>
                <div style="font-size:12px;opacity:0.8;">مؤشرات الجودة السريرية المركبة</div>
            </div>
        </div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <?php
                $qi_recs = [];
                if (($hba1c_controlled['rate'] ?? 0) < 50) $qi_recs[] = ['icon' => '🩸', 'text' => "تحكم HbA1c: {$hba1c_controlled['rate']}% — الهدف >50%"];
                if (($foot_exam_rate['rate'] ?? 0) < 80) $qi_recs[] = ['icon' => '🦶', 'text' => "فحص القدم: {$foot_exam_rate['rate']}% — الهدف >80%"];
                if (($bp_control['rate'] ?? 0) < 60) $qi_recs[] = ['icon' => '❤️', 'text' => "ضبط الضغط: {$bp_control['rate']}% — الهدف >60%"];
                if (($healing_rate['rate'] ?? 0) < 70) $qi_recs[] = ['icon' => '🩹', 'text' => "الشفاء: {$healing_rate['rate']}% — الهدف >70%"];
                if (($smoking_rate['rate'] ?? 0) > 20) $qi_recs[] = ['icon' => '🚬', 'text' => "التدخين: {$smoking_rate['rate']}% — الهدف <15%"];
            ?>
            <?php foreach (array_slice($qi_recs, 0, 3) as $r): ?>
                <span style="background:rgba(255,255,255,0.15);padding:4px 10px;border-radius:20px;font-size:11px;white-space:nowrap;">
                    <?php echo $r['icon']; ?> <?php echo $r['text']; ?>
                </span>
            <?php endforeach; ?>
            <?php if (count($qi_recs) > 3): ?>
                <span style="background:rgba(255,255,255,0.1);padding:4px 10px;border-radius:20px;font-size:11px;">+<?php echo count($qi_recs) - 3; ?> أكثر</span>
            <?php endif; ?>
            <?php if (empty($qi_recs)): ?>
                <span style="background:rgba(255,255,255,0.15);padding:4px 10px;border-radius:20px;font-size:11px;">✅ جميع المؤشرات عند الهدف</span>
            <?php endif; ?>
        </div>
    </div>
</div>

    <a href="?tab=quality&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="<?php echo $active_tab === 'quality' ? 'active' : ''; ?>">📊 جودة الرعاية</a>
    <a href="?tab=population&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="<?php echo $active_tab === 'population' ? 'active' : ''; ?>">👥 صحة السكان</a>
    <a href="?tab=outcomes&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="<?php echo $active_tab === 'outcomes' ? 'active' : ''; ?>">🩹 نتائج العلاج</a>
    <a href="?tab=providers&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="<?php echo $active_tab === 'providers' ? 'active' : ''; ?>">👨‍⚕️ أداء مقدمي الخدمة</a>
    <a href="?tab=insights&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="<?php echo $active_tab === 'insights' ? 'active' : ''; ?>">💡 رؤى ذكية</a>
</div>

<!-- ===================== TAB 1: CLINICAL QUALITY ===================== -->
<?php if ($active_tab === 'quality'): ?>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">📊 مؤشرات جودة الرعاية السريرية</div>
        <span style="font-size:12px;color:var(--text-muted);">Clinical Quality Measures</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
        
        <!-- HbA1c Control -->
        <div class="quality-card">
            <?php 
                $hba1c_pct = $hba1c_controlled['rate'] ?? 0;
                $hba1c_color = $hba1c_pct >= 50 ? '#10b981' : ($hba1c_pct >= 30 ? '#f59e0b' : '#ef4444');
                $hba1c_ring = $hba1c_pct / 100 * 283; // 283 = circumference of r=45
                $hba1c_remaining = 283 - $hba1c_ring;
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $hba1c_color; ?>;color:<?php echo $hba1c_color; ?>;">
                <?php echo $hba1c_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">التحكم بـ HbA1c</div>
            <div class="quality-label">&lt; 7% — <?php echo (int)($hba1c_controlled['controlled'] ?? 0); ?>/<?php echo (int)($hba1c_controlled['total'] ?? 0); ?> مريض</div>
            <div class="quality-target">الهدف: ≥ 50%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($hba1c_pct, 100); ?>%;background:<?php echo $hba1c_color; ?>;"></div></div>
        </div>

        <!-- Foot Exam Rate -->
        <div class="quality-card">
            <?php 
                $foot_pct = $foot_exam_rate['rate'] ?? 0;
                $foot_color = $foot_pct >= 80 ? '#10b981' : ($foot_pct >= 50 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $foot_color; ?>;color:<?php echo $foot_color; ?>;">
                <?php echo $foot_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">فحص القدم</div>
            <div class="quality-label"><?php echo (int)($foot_exam_rate['with_exam'] ?? 0); ?>/<?php echo (int)($foot_exam_rate['total_visits'] ?? 0); ?> زيارة</div>
            <div class="quality-target">الهدف: ≥ 80%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($foot_pct, 100); ?>%;background:<?php echo $foot_color; ?>;"></div></div>
        </div>

        <!-- BP Control -->
        <div class="quality-card">
            <?php 
                $bp_pct = $bp_control['rate'] ?? 0;
                $bp_color = $bp_pct >= 60 ? '#10b981' : ($bp_pct >= 40 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $bp_color; ?>;color:<?php echo $bp_color; ?>;">
                <?php echo $bp_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">التحكم بالضغط</div>
            <div class="quality-label">&lt; 130/80 — <?php echo (int)($bp_control['controlled'] ?? 0); ?>/<?php echo (int)($bp_control['total'] ?? 0); ?></div>
            <div class="quality-target">الهدف: ≥ 60%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($bp_pct, 100); ?>%;background:<?php echo $bp_color; ?>;"></div></div>
        </div>

        <!-- LDL Control -->
        <div class="quality-card">
            <?php 
                $ldl_pct = $ldl_control['rate'] ?? 0;
                $ldl_color = $ldl_pct >= 50 ? '#10b981' : ($ldl_pct >= 30 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $ldl_color; ?>;color:<?php echo $ldl_color; ?>;">
                <?php echo $ldl_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">التحكم بـ LDL</div>
            <div class="quality-label">&lt; 100 — <?php echo (int)($ldl_control['controlled'] ?? 0); ?>/<?php echo (int)($ldl_control['total'] ?? 0); ?></div>
            <div class="quality-target">الهدف: ≥ 50%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($ldl_pct, 100); ?>%;background:<?php echo $ldl_color; ?>;"></div></div>
        </div>

        <!-- Smoking Rate -->
        <div class="quality-card">
            <?php 
                $smk_pct = $smoking_rate['rate'] ?? 0;
                $smk_color = $smk_pct <= 15 ? '#10b981' : ($smk_pct <= 25 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $smk_color; ?>;color:<?php echo $smk_color; ?>;">
                <?php echo $smk_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">معدل التدخين</div>
            <div class="quality-label"><?php echo (int)($smoking_rate['smokers'] ?? 0); ?>/<?php echo (int)($smoking_rate['total'] ?? 0); ?> مريض</div>
            <div class="quality-target">الهدف: ≤ 15%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($smk_pct, 100); ?>%;background:<?php echo $smk_color; ?>;"></div></div>
        </div>

        <!-- Healing Rate -->
        <div class="quality-card">
            <?php 
                $heal_pct = $healing_rate['rate'] ?? 0;
                $heal_color = $heal_pct >= 70 ? '#10b981' : ($heal_pct >= 40 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $heal_color; ?>;color:<?php echo $heal_color; ?>;">
                <?php echo $heal_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">نسبة الشفاء</div>
            <div class="quality-label"><?php echo (int)($healing_rate['healed'] ?? 0); ?>/<?php echo (int)($healing_rate['with_wounds'] ?? 1); ?> مريض</div>
            <div class="quality-target">الهدف: ≥ 70%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($heal_pct, 100); ?>%;background:<?php echo $heal_color; ?>;"></div></div>
        </div>

        <!-- Amputation Prevention -->
        <div class="quality-card">
            <?php 
                $amp_total = $amputation_data['total_wagner3'] ?? 0;
                $amp_prevented_pct = $amp_total > 0 ? round($prevented_amp / $amp_total * 100, 1) : 0;
                $amp_color = $amp_prevented_pct >= 85 ? '#10b981' : ($amp_prevented_pct >= 70 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="quality-ring" style="border:4px solid <?php echo $amp_color; ?>;color:<?php echo $amp_color; ?>;">
                <?php echo $amp_prevented_pct; ?>%
            </div>
            <div style="font-weight:700;font-size:0.9rem;">الوقاية من البتر</div>
            <div class="quality-label"><?php echo $prevented_amp; ?> من <?php echo $amp_total; ?> تم إنقاذها</div>
            <div class="quality-target">الهدف: ≥ 85%</div>
            <div class="metric-bar"><div class="metric-bar-fill" style="width:<?php echo min($amp_prevented_pct, 100); ?>%;background:<?php echo $amp_color; ?>;"></div></div>
        </div>

    </div>
</div>

<!-- Quality Trends -->
<div class="card">
    <div class="card-header"><div class="card-title">📈 اتجاهات الجودة الشهرية</div></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div style="height:200px;"><canvas id="hba1cTrendChart" width="400" height="200"></canvas></div>
        <div style="height:200px;"><canvas id="healingTrendChart" width="400" height="200"></canvas></div>
    </div>
    <div style="display:flex;justify-content:center;gap:2rem;margin-top:0.5rem;font-size:12px;color:var(--text-muted);">
        <span><span style="color:#dc2626;font-weight:700;">●</span> HbA1c المتوسط</span>
        <span><span style="color:#10b981;font-weight:700;">●</span> حالات الشفاء</span>
    </div>
</div>

<!-- ===================== TAB 2: POPULATION HEALTH ===================== -->
<?php elseif ($active_tab === 'population'): ?>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Age Pyramid -->
    <div class="card" style="flex:1.5;min-width:350px;">
        <div class="card-header"><div class="card-title">👤 الهرم السكاني</div></div>
        <div style="height:280px;"><canvas id="agePyramidChart" width="600" height="280"></canvas></div>
    </div>
    <!-- Comorbidity Burden -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">🫀 عبء الأمراض المصاحبة</div></div>
        <div class="spec-stat mb-2">
            <div class="value" style="color:var(--teal);"><?php echo $comorbidity_data['avg_comorbidities'] ?? '—'; ?></div>
            <div class="label">متوسط المضاعفات لكل مريض</div>
        </div>
        <div style="height:160px;"><canvas id="comorbidityChart" width="300" height="160"></canvas></div>
    </div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Geographic Distribution -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">🏙️ التوزيع الجغرافي</div></div>
        <div class="table-container">
            <table style="font-size:13px;">
                <thead><tr><th>المدينة</th><th>العدد</th><th>متوسط المسافة</th></tr></thead>
                <tbody>
                    <?php foreach ($geo_data as $g): ?>
                    <tr><td><?php echo escape_output($g['city']); ?></td>
                        <td><strong><?php echo $g['cnt']; ?></strong></td>
                        <td><?php echo $g['avg_distance'] ? $g['avg_distance'] . ' كم' : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($geo_data)): ?><tr><td colspan="3" style="color:var(--text-muted);">لا توجد بيانات</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Treatment Modalities -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">💊 توزيع طرق العلاج</div></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($treatment_modality as $tm): ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="flex:1;font-size:13px;"><?php echo escape_output($tm['treatment_type']); ?></span>
                <div style="flex:1;height:20px;background:var(--border);border-radius:10px;overflow:hidden;">
                    <?php $tm_pct = $tm['cnt'] / max(array_column($treatment_modality, 'cnt')) * 100; ?>
                    <div style="height:100%;width:<?php echo $tm_pct; ?>%;background:var(--teal);border-radius:10px;"></div>
                </div>
                <span style="font-weight:700;font-size:13px;width:30px;text-align:left;"><?php echo $tm['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===================== TAB 3: OUTCOMES ===================== -->
<?php elseif ($active_tab === 'outcomes'): ?>

<div class="card mb-4">
    <div class="card-header"><div class="card-title">🩹 نتائج العلاج حسب درجة Wagner</div></div>
    <div class="table-container">
        <table style="font-size:13px;">
            <thead>
                <tr><th>درجة Wagner</th><th>إجمالي المرضى</th><th>تم شفاؤهم</th><th>نسبة الشفاء</th><th>متوسط التحسن</th><th>متوسط أيام الشفاء</th></tr>
            </thead>
            <tbody>
                <?php foreach ($wagner_healing as $wh): 
                    $wh_rate = $wh['total'] > 0 ? round($wh['healed'] / $wh['total'] * 100, 1) : 0;
                ?>
                <tr>
                    <td><span class="badge badge-<?php echo $wh['wagner_grade'] >= 3 ? 'danger' : ($wh['wagner_grade'] >= 2 ? 'warning' : 'success'); ?>">Wagner <?php echo $wh['wagner_grade']; ?></span></td>
                    <td><?php echo $wh['total']; ?></td>
                    <td><?php echo $wh['healed']; ?></td>
                    <td><strong style="color:<?php echo $wh_rate >= 70 ? '#10b981' : ($wh_rate >= 40 ? '#f59e0b' : '#ef4444'); ?>"><?php echo $wh_rate; ?>%</strong></td>
                    <td><?php echo $wh['avg_improvement'] ?: '—'; ?>%</td>
                    <td><?php echo $wh['avg_days'] ? $wh['avg_days'] . ' يوم' : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($wagner_healing)): ?><tr><td colspan="6" style="color:var(--text-muted);">لا توجد بيانات كافية</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Wound Cause Analysis -->
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">⚠️ تحليل أسباب الجروح</div></div>
        <div style="height:200px;"><canvas id="woundCauseChart" width="400" height="200"></canvas></div>
    </div>
    <!-- Amputation Types -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">🦿 أنواع البتر</div></div>
        <?php if ($amputation_types): ?>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($amputation_types as $at): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;background:var(--bg-input);border-radius:8px;">
                <span style="font-size:13px;"><?php echo $at['current_amputation']; ?></span>
                <span class="badge badge-danger"><?php echo $at['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:1rem;">لا توجد حالات بتر</p>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== TAB 4: PROVIDER PERFORMANCE ===================== -->
<?php elseif ($active_tab === 'providers'): ?>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">👨‍⚕️ أداء مقدمي الخدمة الطبية</div>
        <form method="get" class="flex gap-2 items-center">
            <input type="hidden" name="tab" value="providers">
            <input type="hidden" name="from_date" value="<?php echo $from_date; ?>">
            <input type="hidden" name="to_date" value="<?php echo $to_date; ?>">
            <select name="doctor_id" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);font-size:12px;font-family:'Tajawal',sans-serif;background:var(--bg-input);color:var(--text-body);">
                <option value="0">جميع الأطباء</option>
                <?php while ($d = $doctors->fetch_assoc()): ?>
                <option value="<?php echo $d['user_id']; ?>" <?php echo $doctor_filter === (int)$d['user_id'] ? 'selected' : ''; ?>><?php echo $d['full_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>
    <?php if ($doctor_performance): ?>
    <div>
        <?php foreach ($doctor_performance as $i => $dp): ?>
        <div class="provider-card">
            <div class="provider-rank"><?php echo $i + 1; ?></div>
            <div style="flex:1;">
                <div style="font-weight:700;"><?php echo escape_output($dp['full_name']); ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?php echo $dp['role']; ?></div>
            </div>
            <div class="spec-stat" style="min-width:60px;">
                <div class="value" style="font-size:1.1rem;color:var(--teal);"><?php echo $dp['total_visits']; ?></div>
                <div class="label">زيارة</div>
            </div>
            <div class="spec-stat" style="min-width:60px;">
                <div class="value" style="font-size:1.1rem;color:var(--blue);"><?php echo $dp['unique_patients']; ?></div>
                <div class="label">مريض</div>
            </div>
            <div class="spec-stat" style="min-width:60px;">
                <div class="value" style="font-size:1.1rem;color:var(--green);"><?php echo $dp['foot_exams']; ?></div>
                <div class="label">فحص قدم</div>
            </div>
            <div class="spec-stat" style="min-width:60px;">
                <div class="value" style="font-size:1.1rem;color:var(--gold);"><?php echo $dp['healed'] ?: 0; ?></div>
                <div class="label">تم شفاؤه</div>
            </div>
            <div class="spec-stat" style="min-width:60px;">
                <div class="value" style="font-size:1.1rem;color:var(--orange);"><?php echo $dp['avg_improvement'] ? $dp['avg_improvement'] . '%' : '—'; ?></div>
                <div class="label">متوسط التحسن</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--text-muted);text-align:center;padding:2rem;">لا توجد بيانات أداء للمقدمين في هذه الفترة</p>
    <?php endif; ?>
</div>

<!-- Provider comparative chart -->
<div class="card">
    <div class="card-header"><div class="card-title">📊 مقارنة أداء مقدمي الخدمة</div></div>
    <div style="height:250px;"><canvas id="providerChart" width="800" height="250"></canvas></div>
</div>

<!-- ===================== TAB 5: CLINICAL INSIGHTS ===================== -->
<?php elseif ($active_tab === 'insights'): ?>

<!-- Actionable Insights -->
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">💡 رؤى سريرية قابلة للتنفيذ</div>
        <span style="font-size:12px;color:var(--text-muted);">Clinical Decision Support</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;">
        <?php foreach ($actionable as $insight): ?>
        <div class="insight-banner insight-<?php echo $insight['type']; ?>">
            <span style="font-size:1.5rem;"><?php echo $insight['icon']; ?></span>
            <div>
                <strong><?php echo $insight['title']; ?></strong>
                <br><span style="font-size:12px;"><?php echo $insight['desc']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($actionable)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:1rem;">لا توجد رؤى متاحة حالياً — بيانات غير كافية</p>
        <?php endif; ?>
    </div>
</div>

<!-- High-Risk Patient List -->
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">🆘 مرضى الخطر المرتفع — يحتاجون تدخل فوري</div>
        <a href="<?php echo BASE_URL; ?>/modules/analytics/risk_alerts.php" class="btn btn-sm btn-danger">عرض الكل</a>
    </div>
    <?php if ($high_risk_patients->num_rows > 0): ?>
    <div class="table-container">
        <table style="font-size:13px;">
            <thead>
                <tr>
                    <th>المريض</th>
                    <th>Wagner</th>
                    <th>HbA1c</th>
                    <th>تدخين</th>
                    <th>الإحساس</th>
                    <th>آخر زيارة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($hr = $high_risk_patients->fetch_assoc()): ?>
                <tr>
                    <td><a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $hr['patient_id']; ?>" style="font-weight:600;"><?php echo escape_output($hr['full_name']); ?></a><br><span style="font-size:11px;color:var(--text-muted);"><?php echo $hr['file_number']; ?></span></td>
                    <td><span class="badge badge-<?php echo $hr['wagner_grade'] >= 3 ? 'danger' : 'warning'; ?>"><?php echo $hr['wagner_grade']; ?></span></td>
                    <td><?php echo $hr['hba1c_value'] ? '<span class="badge ' . ($hr['hba1c_value'] > 7 ? 'badge-danger' : 'badge-success') . '">' . $hr['hba1c_value'] . '%</span>' : '—'; ?></td>
                    <td><?php echo $hr['smoking_status'] === 'مدخن' ? '🚬' : ($hr['smoking_status'] === 'سابق' ? '🚭' : '—'); ?></td>
                    <td><?php 
                        $sens = [];
                        if ($hr['right_sensation'] === 'معدوم') $sens[] = 'يمنى';
                        if ($hr['left_sensation'] === 'معدوم') $sens[] = 'يسرى';
                        echo $sens ? '❌ ' . implode(', ', $sens) : '—';
                    ?></td>
                    <td style="font-size:11px;"><?php echo $hr['last_visit'] ?: '—'; ?></td>
                    <td><a href="<?php echo BASE_URL; ?>/modules/patients/timeline.php?id=<?php echo $hr['patient_id']; ?>" class="btn btn-sm btn-primary">عرض</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p style="color:var(--text-muted);text-align:center;padding:1rem;">لا يوجد مرضى خطر مرتفع حالياً</p>
    <?php endif; ?>
</div>

<!-- Key Metrics Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
    <div class="stat-card">
        <div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
        <div class="stat-value" style="font-size:1.3rem;color:#7c3aed;"><?php echo $missed_count; ?></div>
        <div class="stat-label">مريض لم يزور &gt;90 يوماً</div>
    </div>
    <div class="stat-card">
        <div class="stat-bar" style="background:linear-gradient(90deg,#ef4444,#dc2626);"></div>
        <div class="stat-value" style="font-size:1.3rem;color:#dc2626;"><?php echo $hba1c_up; ?></div>
        <div class="stat-label">مريض HbA1c &gt;9%</div>
    </div>
    <div class="stat-card">
        <div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
        <div class="stat-value" style="font-size:1.3rem;color:#d97706;"><?php echo $sensory_loss; ?></div>
        <div class="stat-label">فقدان حس كامل</div>
    </div>
    <div class="stat-card">
        <div class="stat-bar" style="background:linear-gradient(90deg,#0ea5e9,#0284c7);"></div>
        <div class="stat-value" style="font-size:1.3rem;color:#0284c7;"><?php echo $smoking_ulcer; ?></div>
        <div class="stat-label">مدخن + جرح قدم</div>
    </div>
    <div class="stat-card">
        <div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
        <div class="stat-value" style="font-size:1.3rem;color:#059669;"><?php echo $prevented_amp; ?></div>
        <div class="stat-label">تم إنقاذها من البتر</div>
    </div>
</div>

<?php endif; ?>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($active_tab === 'quality'): ?>
    // HbA1c Trend
    const hba1cTrend = <?php echo json_encode($hba1c_trend ?? [], JSON_UNESCAPED_UNICODE); ?>;
    if (hba1cTrend.length > 0) {
        const tc = new ClinicChart('hba1cTrendChart');
        tc.drawLineChart(
            hba1cTrend.map(d => d.month.slice(5)),
            hba1cTrend.map(d => parseFloat(d.avg_hba1c)),
            { lineColor: '#dc2626', fillColor: 'rgba(220,38,38,0.08)' }
        );
    }
    // Healing Trend
    const healingTrend = <?php echo json_encode($healing_trend, JSON_UNESCAPED_UNICODE); ?>;
    if (healingTrend.length > 0) {
        const hc = new ClinicChart('healingTrendChart');
        hc.drawBarChart(
            healingTrend.map(d => d.month.slice(5)),
            healingTrend.map(d => parseInt(d.cnt)),
            { colors: ['#10b981'] }
        );
    }
    <?php elseif ($active_tab === 'population'): ?>
    // Age Pyramid
    const ageData = <?php echo json_encode($age_pyramid, JSON_UNESCAPED_UNICODE); ?>;
    if (ageData.length > 0) {
        const groups = [...new Set(ageData.map(d => d.age_group))].sort();
        const maleData = groups.map(g => { const r = ageData.find(d => d.age_group === g && d.gender === 'ذكر'); return r ? parseInt(r.cnt) : 0; });
        const femaleData = groups.map(g => { const r = ageData.find(d => d.age_group === g && d.gender === 'أنثى'); return r ? parseInt(r.cnt) : 0; });
        // Draw as two-sided bar chart
        const canvas = document.getElementById('agePyramidChart');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            const w = canvas.width, h = canvas.height;
            const padding = { top: 20, bottom: 40, left: 80, right: 40 };
            const chartH = h - padding.top - padding.bottom;
            const barH = Math.min(chartH / groups.length - 4, 30);
            
            ctx.clearRect(0, 0, w, h);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            
            const maxVal = Math.max(...maleData, ...femaleData, 1);
            
            groups.forEach((g, i) => {
                const y = padding.top + i * (barH + 4);
                const mVal = maleData[i] || 0;
                const fVal = femaleData[i] || 0;
                const cx = w / 2;
                
                // Male bar (right side, blue)
                const mW = (mVal / maxVal) * (cx - padding.left - 20);
                ctx.fillStyle = '#3b82f6';
                ctx.beginPath();
                ctx.roundRect(cx + 5, y, mW, barH, [0, 4, 4, 0]);
                ctx.fill();
                
                // Female bar (left side, pink)
                const fW = (fVal / maxVal) * (cx - padding.right - 20);
                ctx.fillStyle = '#ec4899';
                ctx.beginPath();
                ctx.roundRect(cx - 5 - fW, y, fW, barH, [4, 0, 0, 4]);
                ctx.fill();
                
                // Label
                ctx.fillStyle = '#64748b';
                ctx.font = '11px Tajawal, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(g, cx, y + barH + 14);
                
                // Values
                ctx.font = '10px Tajawal, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillStyle = '#3b82f6';
                ctx.fillText(mVal > 0 ? mVal : '', cx - 10, y + barH / 2 + 4);
                ctx.textAlign = 'left';
                ctx.fillStyle = '#ec4899';
                ctx.fillText(fVal > 0 ? fVal : '', cx + 10, y + barH / 2 + 4);
            });
            
            // Labels
            ctx.fillStyle = '#3b82f6';
            ctx.font = 'bold 11px Tajawal, sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText('♂ ذكور', cx + 10, 14);
            ctx.fillStyle = '#ec4899';
            ctx.textAlign = 'right';
            ctx.fillText('♀ إناث', cx - 10, 14);
        }
    }
    // Comorbidity chart
    const comorbidityData = <?php echo json_encode($comorbidity_dist, JSON_UNESCAPED_UNICODE); ?>;
    if (comorbidityData.length > 0) {
        const cc = new ClinicChart('comorbidityChart');
        cc.drawBarChart(
            comorbidityData.map(d => d.comorbidity_count + ' مضاعفات'),
            comorbidityData.map(d => parseInt(d.cnt)),
            { colors: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#ec4899'] }
        );
    }
    <?php elseif ($active_tab === 'outcomes'): ?>
    // Wound Cause Chart
    const woundData = <?php echo json_encode($wound_causes, JSON_UNESCAPED_UNICODE); ?>;
    if (woundData.length > 0) {
        const wc = new ClinicChart('woundCauseChart');
        wc.drawPieChart(
            woundData.map(d => ({ label: d.initial_cause, value: parseInt(d.cnt) })),
            { donut: true }
        );
    }
    <?php elseif ($active_tab === 'providers'): ?>
    // Provider Chart
    const providerData = <?php echo json_encode($doctor_performance, JSON_UNESCAPED_UNICODE); ?>;
    if (providerData.length > 0) {
        const pc = new ClinicChart('providerChart');
        pc.drawBarChart(
            providerData.map(d => d.full_name.split(' ')[0]),
            providerData.map(d => parseInt(d.total_visits)),
            { colors: ['#0a7e6e', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#10b981'] }
        );
    }
    <?php endif; ?>
});
</script>

