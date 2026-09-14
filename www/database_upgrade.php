<?php
/**
 * Database Optimization Upgrade
 * Adds indexes, creates patient_summary and risk_score_history tables
 * Run ONCE after installing the system.
 * Safe to re-run — uses IF NOT EXISTS / IF EXISTS checks.
 */

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html dir='rtl' lang='ar'><head><meta charset='utf-8'><title>ترقية قاعدة البيانات</title>";
echo "<style>body{font-family:Tajawal,sans-serif;max-width:800px;margin:40px auto;padding:20px;direction:rtl;}";
echo ".ok{color:#059669;padding:4px 0}.err{color:#dc2626;padding:4px 0}.section{background:#f8fafc;padding:12px;border-radius:8px;margin:8px 0}</style></head><body>";
echo "<h1>🗄️ ترقية قاعدة البيانات</h1><p>Database Optimization Upgrade</p><hr>";

$success = 0;
$errors = 0;

function run($mysqli, $sql, $label) {
    global $success, $errors;
    if ($mysqli->query($sql)) {
        echo "<div class='ok'>✅ {$label}</div>";
        $success++;
    } else {
        // Ignore "Duplicate key name" errors for indexes
        if (strpos($mysqli->error, 'Duplicate key name') !== false || 
            strpos($mysqli->error, 'already exists') !== false) {
            echo "<div class='ok'>✅ {$label} (موجود مسبقاً)</div>";
            $success++;
        } else {
            echo "<div class='err'>❌ {$label}: " . $mysqli->error . "</div>";
            $errors++;
        }
    }
}

// ===== SECTION 1: INDEXES =====
echo "<div class='section'><h2>📌 إضافة الفهارس</h2>";

// visits indexes
run($mysqli, "ALTER TABLE visits ADD INDEX idx_visits_patient_id (patient_id)", "فهرس visits.patient_id");
run($mysqli, "ALTER TABLE visits ADD INDEX idx_visits_visit_date (visit_date)", "فهرس visits.visit_date");
run($mysqli, "ALTER TABLE visits ADD INDEX idx_visits_created_by (created_by)", "فهرس visits.created_by");
run($mysqli, "ALTER TABLE visits ADD INDEX idx_visits_patient_date (patient_id, visit_date)", "فهرس مركب visits (patient_id, visit_date)");

// patients indexes
run($mysqli, "ALTER TABLE patients ADD INDEX idx_patients_is_active (is_active)", "فهرس patients.is_active");
run($mysqli, "ALTER TABLE patients ADD INDEX idx_patients_city (city)", "فهرس patients.city");
run($mysqli, "ALTER TABLE patients ADD INDEX idx_patients_age (age)", "فهرس patients.age");
run($mysqli, "ALTER TABLE patients ADD INDEX idx_patients_gender (gender)", "فهرس patients.gender");

// medical_history indexes
run($mysqli, "ALTER TABLE medical_history ADD INDEX idx_mh_patient_id (patient_id)", "فهرس medical_history.patient_id");
run($mysqli, "ALTER TABLE medical_history ADD INDEX idx_mh_smoking (smoking_status)", "فهرس medical_history.smoking_status");

// child table indexes (visit_id foreign keys)
run($mysqli, "ALTER TABLE vital_signs ADD INDEX idx_vs_visit_id (visit_id)", "فهرس vital_signs.visit_id");
run($mysqli, "ALTER TABLE blood_sugar_readings ADD INDEX idx_bsr_visit_id (visit_id)", "فهرس blood_sugar_readings.visit_id");
run($mysqli, "ALTER TABLE treatments ADD INDEX idx_treat_visit_id (visit_id)", "فهرس treatments.visit_id");
run($mysqli, "ALTER TABLE complications ADD INDEX idx_comp_patient_id (patient_id)", "فهرس complications.patient_id");
run($mysqli, "ALTER TABLE lab_results ADD INDEX idx_lr_visit_id (visit_id)", "فهرس lab_results.visit_id");
run($mysqli, "ALTER TABLE foot_assessments ADD INDEX idx_fa_visit_id (visit_id)", "فهرس foot_assessments.visit_id");
run($mysqli, "ALTER TABLE foot_assessments ADD INDEX idx_fa_wagner (wagner_grade)", "فهرس foot_assessments.wagner_grade");
run($mysqli, "ALTER TABLE foot_ulcers ADD INDEX idx_fu_visit_id (visit_id)", "فهرس foot_ulcers.visit_id");
run($mysqli, "ALTER TABLE outcomes ADD INDEX idx_out_visit_id (visit_id)", "فهرس outcomes.visit_id");
run($mysqli, "ALTER TABLE outcomes ADD INDEX idx_out_improvement (improvement_percentage)", "فهرس outcomes.improvement_percentage");
run($mysqli, "ALTER TABLE care_plan ADD INDEX idx_cp_visit_id (visit_id)", "فهرس care_plan.visit_id");
run($mysqli, "ALTER TABLE care_sessions ADD INDEX idx_cs_visit_id (visit_id)", "فهرس care_sessions.visit_id");

echo "</div>";

// ===== SECTION 2: PATIENT SUMMARY TABLE =====
echo "<div class='section'><h2>📊 إنشاء جدول ملخص المرضى (patient_summary)</h2>";
echo "<p style='font-size:13px;color:#64748b;'>يخزن أحدث القيم السريرية لكل مريض لتسريع الاستعلامات المتكررة</p>";

$mysqli->query("DROP TABLE IF EXISTS patient_summary");
run($mysqli, "CREATE TABLE IF NOT EXISTS patient_summary (
    summary_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150),
    file_number VARCHAR(20),
    age INT,
    gender ENUM('ذكر', 'أنثى'),
    city VARCHAR(50),
    smoking_status ENUM('لا', 'مدخن', 'سابق'),
    diabetes_type VARCHAR(100),
    duration_years INT,
    
    -- Latest clinical values
    latest_visit_date DATE,
    latest_hba1c DECIMAL(4,1),
    latest_fpg DECIMAL(5,1),
    latest_wagner_grade TINYINT,
    latest_wound_size DECIMAL(5,1),
    latest_wound_condition VARCHAR(50),
    latest_improvement TINYINT,
    latest_bmi DECIMAL(4,1),
    latest_bp_systolic INT,
    latest_bp_diastolic INT,
    latest_ldl DECIMAL(5,1),
    latest_creatinine DECIMAL(4,2),
    latest_abpi_right DECIMAL(4,2),
    latest_abpi_left DECIMAL(4,2),
    latest_treatment_type TEXT,
    latest_amputation VARCHAR(50),
    latest_healing_date DATE,
    
    -- Aggregates
    total_visits INT DEFAULT 0,
    total_assessments INT DEFAULT 0,
    total_ulcers INT DEFAULT 0,
    days_since_last_visit INT,
    visit_frequency_score DECIMAL(4,1),
    
    -- Risk
    risk_score INT DEFAULT 0,
    risk_class VARCHAR(50),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "جدول ملخص المرضى");

echo "</div>";

// ===== SECTION 3: RISK SCORE HISTORY TABLE =====
echo "<div class='section'><h2>📈 إنشاء جدول تاريخ درجات الخطورة (risk_score_history)</h2>";
echo "<p style='font-size:13px;color:#64748b;'>يسجل درجات الخطر بشكل دوري لتتبع الاتجاهات</p>";

run($mysqli, "CREATE TABLE IF NOT EXISTS risk_score_history (
    risk_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    probability DECIMAL(5,1),
    risk_class VARCHAR(50),
    wagner_grade TINYINT,
    hba1c_value DECIMAL(4,1),
    gap_days INT,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rsh_patient (patient_id),
    INDEX idx_rsh_recorded (recorded_at),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "جدول تاريخ درجات الخطورة");

echo "</div>";

// ===== SECTION 4: POPULATE PATIENT SUMMARY =====
echo "<div class='section'><h2>🔄 تعبئة بيانات ملخص المرضى</h2>";

$result = $mysqli->query("SELECT patient_id FROM patients WHERE is_active = 1");
$count = 0;
$total = $result->num_rows;

while ($p = $result->fetch_assoc()) {
    $pid = $p['patient_id'];
    $count++;
    
    // Get latest visit data
    $latest = $mysqli->query("SELECT v.visit_date, v.created_by,
        bs.hba1c_value, bs.fpg_value,
        fa.wagner_grade, fa.abpi_right, fa.abpi_left,
        fu.wound_size_cm2, fu.wound_condition,
        o.improvement_percentage, o.current_amputation, o.healing_date,
        vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
        lr.ldl, lr.creatinine,
        t.treatment_type
        FROM visits v
        LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
        LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
        LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
        LEFT JOIN outcomes o ON v.visit_id = o.visit_id
        LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
        LEFT JOIN lab_results lr ON v.visit_id = lr.visit_id
        LEFT JOIN treatments t ON v.visit_id = t.visit_id
        WHERE v.patient_id = $pid
        ORDER BY v.visit_date DESC LIMIT 1")->fetch_assoc();
    
    // Get patient info
    $patient = $mysqli->query("SELECT p.*, mh.smoking_status, mh.diabetes_type, mh.duration_years
        FROM patients p LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
        WHERE p.patient_id = $pid")->fetch_assoc();
    
    // Get aggregates
    $agg = $mysqli->query("SELECT 
        COUNT(*) as total_visits,
        (SELECT COUNT(*) FROM foot_assessments fa2 JOIN visits v2 ON fa2.visit_id = v2.visit_id WHERE v2.patient_id = $pid) as assessments,
        (SELECT COUNT(*) FROM foot_ulcers fu2 JOIN visits v2 ON fu2.visit_id = v2.visit_id WHERE v2.patient_id = $pid) as ulcers,
        DATEDIFF(CURDATE(), COALESCE((SELECT MAX(v3.visit_date) FROM visits v3 WHERE v3.patient_id = $pid), CURDATE())) as gap
        FROM visits WHERE patient_id = $pid")->fetch_assoc();
    
    // Calculate visit frequency (visits per month over last 12 months)
    $freq = $mysqli->query("SELECT COUNT(*) / 12.0 as freq FROM visits WHERE patient_id = $pid AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)")->fetch_assoc();
    
    // Calculate risk score
    $risk_score = 0;
    if (!empty($latest['hba1c_value']) && $latest['hba1c_value'] > 7) $risk_score += 20;
    if (!empty($latest['hba1c_value']) && $latest['hba1c_value'] > 9) $risk_score += 15;
    if (!empty($latest['wagner_grade']) && $latest['wagner_grade'] >= 3) $risk_score += 30;
    if (!empty($latest['wagner_grade']) && $latest['wagner_grade'] >= 4) $risk_score += 10;
    if (($patient['smoking_status'] ?? '') === 'مدخن') $risk_score += 15;
    if ($agg['gap'] > 90) $risk_score += 20;
    elseif ($agg['gap'] > 60) $risk_score += 10;
    
    $risk_class = 'منخفض';
    if ($risk_score >= 70) $risk_class = 'مرتفع';
    elseif ($risk_score >= 40) $risk_class = 'متوسط';
    
    // Direct query approach for bulk operation
    $city_esc = $mysqli->real_escape_string($patient['city'] ?? '');
    $diabetes_esc = $mysqli->real_escape_string($patient['diabetes_type'] ?? '');
    $treatment_esc = $mysqli->real_escape_string($latest['treatment_type'] ?? '');
    $wound_esc = $mysqli->real_escape_string($latest['wound_condition'] ?? '');
    $amp_esc = $mysqli->real_escape_string($latest['current_amputation'] ?? '');
    $name_esc = $mysqli->real_escape_string($patient['full_name'] ?? '');
    $file_esc = $mysqli->real_escape_string($patient['file_number'] ?? '');
    
    // Handle ENUM fields with fallback to NULL
    $gender_val = in_array($patient['gender'] ?? '', ['ذكر', 'أنثى']) ? "'" . $mysqli->real_escape_string($patient['gender']) . "'" : 'NULL';
    $smoking_val = in_array($patient['smoking_status'] ?? '', ['لا', 'مدخن', 'سابق']) ? "'" . $mysqli->real_escape_string($patient['smoking_status']) . "'" : 'NULL';
    
    $hba1c = !empty($latest['hba1c_value']) ? "'{$latest['hba1c_value']}'" : "NULL";
    $fpg = !empty($latest['fpg_value']) ? "'{$latest['fpg_value']}'" : "NULL";
    $wagner = isset($latest['wagner_grade']) && $latest['wagner_grade'] !== null ? "'{$latest['wagner_grade']}'" : "NULL";
    $wound_size = !empty($latest['wound_size_cm2']) ? "'{$latest['wound_size_cm2']}'" : "NULL";
    $improvement = isset($latest['improvement_percentage']) && $latest['improvement_percentage'] !== null ? "'{$latest['improvement_percentage']}'" : "NULL";
    $bmi = !empty($latest['bmi']) ? "'{$latest['bmi']}'" : "NULL";
    $bp_sys = !empty($latest['blood_pressure_systolic']) ? "'{$latest['blood_pressure_systolic']}'" : "NULL";
    $bp_dia = !empty($latest['blood_pressure_diastolic']) ? "'{$latest['blood_pressure_diastolic']}'" : "NULL";
    $ldl = !empty($latest['ldl']) ? "'{$latest['ldl']}'" : "NULL";
    $creat = !empty($latest['creatinine']) ? "'{$latest['creatinine']}'" : "NULL";
    $abpi_r = !empty($latest['abpi_right']) ? "'{$latest['abpi_right']}'" : "NULL";
    $abpi_l = !empty($latest['abpi_left']) ? "'{$latest['abpi_left']}'" : "NULL";
    $heal_date = !empty($latest['healing_date']) ? "'{$latest['healing_date']}'" : "NULL";
    $visit_date = !empty($latest['visit_date']) ? "'{$latest['visit_date']}'" : "NULL";
    $freq_val = !empty($freq['freq']) ? round($freq['freq'], 1) : 0;

    $sql = "INSERT INTO patient_summary 
        (patient_id, full_name, file_number, age, gender, city, smoking_status, diabetes_type, duration_years,
         latest_visit_date, latest_hba1c, latest_fpg, latest_wagner_grade, latest_wound_size, latest_wound_condition,
         latest_improvement, latest_bmi, latest_bp_systolic, latest_bp_diastolic, latest_ldl, latest_creatinine,
         latest_abpi_right, latest_abpi_left, latest_treatment_type, latest_amputation, latest_healing_date,
         total_visits, total_assessments, total_ulcers, days_since_last_visit, visit_frequency_score,
         risk_score, risk_class)
    VALUES ({$pid}, '{$name_esc}', '{$file_esc}', " . ((int)$patient['age'] ?: "NULL") . ",
            {$gender_val}, '{$city_esc}',
            {$smoking_val}, '{$diabetes_esc}', " . ((int)$patient['duration_years'] ?: "NULL") . ",
            {$visit_date}, {$hba1c}, {$fpg}, {$wagner}, {$wound_size}, '{$wound_esc}',
            {$improvement}, {$bmi}, {$bp_sys}, {$bp_dia}, {$ldl}, {$creat},
            {$abpi_r}, {$abpi_l}, '{$treatment_esc}', '{$amp_esc}', {$heal_date},
            {$agg['total_visits']}, {$agg['assessments']}, {$agg['ulcers']}, {$agg['gap']}, {$freq_val},
            {$risk_score}, '{$risk_class}')
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name), latest_hba1c = VALUES(latest_hba1c),
        latest_wagner_grade = VALUES(latest_wagner_grade), risk_score = VALUES(risk_score),
        risk_class = VALUES(risk_class), total_visits = VALUES(total_visits),
        latest_fpg = VALUES(latest_fpg), latest_wound_size = VALUES(latest_wound_size),
        latest_wound_condition = VALUES(latest_wound_condition), latest_improvement = VALUES(latest_improvement),
        latest_bmi = VALUES(latest_bmi), latest_bp_systolic = VALUES(latest_bp_systolic),
        latest_bp_diastolic = VALUES(latest_bp_diastolic), latest_ldl = VALUES(latest_ldl),
        latest_creatinine = VALUES(latest_creatinine), latest_abpi_right = VALUES(latest_abpi_right),
        latest_abpi_left = VALUES(latest_abpi_left), latest_treatment_type = VALUES(latest_treatment_type),
        latest_amputation = VALUES(latest_amputation), latest_healing_date = VALUES(latest_healing_date),
        days_since_last_visit = VALUES(days_since_last_visit), visit_frequency_score = VALUES(visit_frequency_score),
        total_assessments = VALUES(total_assessments), total_ulcers = VALUES(total_ulcers),
        latest_visit_date = VALUES(latest_visit_date)";
    
    if ($mysqli->query($sql)) {
        if ($count % 50 === 0) echo "<div class='ok'>✅ تم تعبئة {$count}/{$total} مريض...</div>";
    }
}

echo "<div class='ok'>✅ تم تعبئة {$count} مريض في جدول patient_summary</div>";
echo "</div>";

// ===== SUMMARY =====
echo "<hr><h2>✅ اكتملت الترقية</h2>";
echo "<p>تم بنجاح: {$success} عملية | أخطاء: {$errors}</p>";
echo "<p><a href='index.php' style='display:inline-block;padding:8px 20px;background:#0a7e6e;color:#fff;border-radius:8px;text-decoration:none;'>🔙 العودة للنظام</a></p>";
echo "</body></html>";

$mysqli->close();
?>