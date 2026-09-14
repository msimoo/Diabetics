<?php
/**
 * Auto Instructions Generator — Task 6
 * Generates personalized instructions for a patient based on their data
 */
 
/**
 * Generate auto-instructions for a patient
 * @param mysqli $mysqli Database connection
 * @param int $patient_id Patient ID
 * @return array Array of matched instructions
 */
function generate_patient_instructions($mysqli, $patient_id) {
    // Get patient data
    $patient = $mysqli->query("SELECT * FROM patients WHERE patient_id = $patient_id")->fetch_assoc();
    if (!$patient) return [];
    
    // Get medical history
    $history = $mysqli->query("SELECT * FROM medical_history WHERE patient_id = $patient_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
    
    // Get latest blood sugar readings
    $latest_bs = $mysqli->query("
        SELECT bsr.* FROM blood_sugar_readings bsr 
        JOIN visits v ON bsr.visit_id = v.visit_id 
        WHERE v.patient_id = $patient_id 
        ORDER BY v.visit_date DESC LIMIT 1
    ")->fetch_assoc();
    
    // Get latest foot assessment (Wagner grade)
    $latest_foot = $mysqli->query("
        SELECT fa.* FROM foot_assessments fa 
        JOIN visits v ON fa.visit_id = v.visit_id 
        WHERE v.patient_id = $patient_id 
        ORDER BY v.visit_date DESC LIMIT 1
    ")->fetch_assoc();
    
    // Check if patient has active wound
    $has_wound = $mysqli->query("
        SELECT COUNT(*) as cnt FROM foot_ulcers fu
        JOIN visits v ON fu.visit_id = v.visit_id
        LEFT JOIN outcomes o ON v.visit_id = o.visit_id
        WHERE v.patient_id = $patient_id 
        AND (o.improvement_percentage IS NULL OR o.improvement_percentage < 100)
    ")->fetch_assoc()['cnt'] > 0 ? 1 : 0;
    
    // Build matching criteria
    $hba1c = $latest_bs ? (float)$latest_bs['hba1c_value'] : null;
    $wagner = $latest_foot ? (int)$latest_foot['wagner_grade'] : null;
    $diabetes_type = $history ? $history['diabetes_type'] : null;
    $smoking = $history ? $history['smoking_status'] : null;
    
    // Query matching instructions
    $conditions = ["is_active = 1"];
    
    // Diabetes type match (if specified)
    if ($diabetes_type) {
        $escaped_type = $mysqli->real_escape_string($diabetes_type);
        $conditions[] = "(diabetes_type IS NULL OR diabetes_type = '' OR diabetes_type = '$escaped_type')";
    } else {
        $conditions[] = "(diabetes_type IS NULL OR diabetes_type = '')";
    }
    
    // HbA1c match (if instruction has HbA1c criteria)
    if ($hba1c !== null) {
        $conditions[] = "(
            (min_hba1c IS NULL AND max_hba1c IS NULL)
            OR (min_hba1c IS NOT NULL AND $hba1c >= min_hba1c AND max_hba1c IS NULL)
            OR (max_hba1c IS NOT NULL AND $hba1c <= max_hba1c AND min_hba1c IS NULL)
            OR (min_hba1c IS NOT NULL AND max_hba1c IS NOT NULL AND $hba1c >= min_hba1c AND $hba1c <= max_hba1c)
        )";
    }
    
    // Wagner grade match
    if ($wagner !== null) {
        $conditions[] = "(
            (min_wagner IS NULL AND max_wagner IS NULL)
            OR (min_wagner IS NOT NULL AND $wagner >= min_wagner AND max_wagner IS NULL)
            OR (max_wagner IS NOT NULL AND $wagner <= max_wagner AND min_wagner IS NULL)
            OR (min_wagner IS NOT NULL AND max_wagner IS NOT NULL AND $wagner >= min_wagner AND $wagner <= max_wagner)
        )";
    }
    
    // Wound match
    $conditions[] = "(
        has_wound IS NULL OR has_wound = $has_wound
    )";
    
    // Smoking match
    if ($smoking) {
        $escaped_smoking = $mysqli->real_escape_string($smoking);
        $conditions[] = "(smoking_status IS NULL OR smoking_status = '' OR smoking_status = '$escaped_smoking')";
    }
    
    $where = implode(" AND ", $conditions);
    $result = $mysqli->query("SELECT * FROM auto_instructions WHERE $where ORDER BY sort_order ASC, category ASC");
    
    $instructions = [];
    while ($row = $result->fetch_assoc()) {
        $instructions[] = $row;
    }
    
    return $instructions;
}

/**
 * Render instructions as HTML
 */
function render_instructions_html($instructions) {
    if (empty($instructions)) {
        return '<p style="color:#94a3b8;text-align:center;padding:20px;">لا توجد تعليمات متطابقة مع حالة المريض</p>';
    }
    
    $html = '';
    $current_cat = '';
    
    $category_icons = [
        'عام' => '📋', 'تغذية' => '🥗', 'عناية قدم' => '🦶', 
        'أدوية' => '💊', 'طوارئ' => '🚨', 'تمارين' => '🏃'
    ];
    
    foreach ($instructions as $inst) {
        if ($inst['category'] !== $current_cat) {
            $current_cat = $inst['category'];
            $icon = $category_icons[$current_cat] ?? '📋';
            $html .= "<div style='margin-top:16px;margin-bottom:8px;'>";
            $html .= "<span class='badge badge-info' style='font-size:14px;padding:6px 16px;'>{$icon} {$current_cat}</span>";
            $html .= "</div>";
        }
        
        $html .= "<div style='background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:10px;border-right:4px solid var(--teal);'>";
        $html .= "<h4 style='color:var(--teal);margin-bottom:8px;font-family:\"Cairo\",sans-serif;'>" . escape_output($inst['title_ar']) . "</h4>";
        $html .= "<div style='white-space:pre-line;font-size:14px;line-height:1.8;'>" . escape_output($inst['content_ar']) . "</div>";
        $html .= "</div>";
    }
    
    return $html;
}
?>
