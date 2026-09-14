<?php
/**
 * Smart Analytics API v2
 * Returns JSON analytics data with predictive, similarity, and insight endpoints
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();

$action = $_GET['action'] ?? '';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

switch ($action) {

    // ===================== PREDICT RISK =====================
    case 'predict_risk':
        if (!$patient_id) { echo json_encode(['error' => 'No patient ID']); exit; }

        // Gather full clinical data
        $query = "SELECT 
            mh.smoking_status, mh.diabetes_type, mh.duration_years, mh.physical_activity,
            fa.wagner_grade, fa.right_sensation, fa.left_sensation,
            fa.right_pulse, fa.left_pulse, fa.abpi_right, fa.abpi_left,
            bs.hba1c_value, bs.fpg_value,
            fu.wound_condition, fu.initial_cause, fu.wound_size_cm2, fu.wound_depth,
            vs.bmi, vs.blood_pressure_systolic,
            o.improvement_percentage, o.current_amputation,
            c.has_retinopathy, c.has_nephropathy, c.has_neuropathy, c.has_cad, c.has_cva, c.has_pad,
            lr.ldl, lr.creatinine,
            (SELECT COUNT(*) FROM visits v2 WHERE v2.patient_id = ?) as total_visits,
            DATEDIFF(CURDATE(), (SELECT MAX(v3.visit_date) FROM visits v3 WHERE v3.patient_id = ?)) as gap_days
        FROM patients p
        LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
        LEFT JOIN visits v ON p.patient_id = v.patient_id
        LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
        LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
        LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
        LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
        LEFT JOIN outcomes o ON v.visit_id = o.visit_id
        LEFT JOIN complications c ON p.patient_id = c.patient_id
        LEFT JOIN lab_results lr ON v.visit_id = lr.visit_id
        WHERE p.patient_id = ?
        ORDER BY v.visit_date DESC LIMIT 1";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('iii', $patient_id, $patient_id, $patient_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if (!$data) {
            echo json_encode(['score' => 0, 'probability' => 0, 'class' => 'غير معروف', 'color' => '#94a3b8', 'factors' => [], 'forecast' => []]);
            exit;
        }

        $score = 0;
        $factors = [];
        $features = [];

        // === WEIGHTED SCORING with probability ===
        // 1. HbA1c
        $hba1c = (float)($data['hba1c_value'] ?? 0);
        if ($hba1c > 9) { $score += 25; $factors[] = ['factor' => 'HbA1c > 9%', 'weight' => 25, 'impact' => 'high']; }
        elseif ($hba1c > 7) { $score += 15; $factors[] = ['factor' => 'HbA1c مرتفع', 'weight' => 15, 'impact' => 'medium']; }

        // 2. Wagner grade
        $wagner = (int)($data['wagner_grade'] ?? 0);
        if ($wagner >= 4) { $score += 30; $factors[] = ['factor' => 'Wagner 4-5', 'weight' => 30, 'impact' => 'critical']; }
        elseif ($wagner >= 3) { $score += 22; $factors[] = ['factor' => 'Wagner 3', 'weight' => 22, 'impact' => 'high']; }
        elseif ($wagner >= 2) { $score += 12; $factors[] = ['factor' => 'Wagner 2', 'weight' => 12, 'impact' => 'medium']; }

        // 3. Sensation loss
        if (($data['right_sensation'] ?? '') === 'معدوم' || ($data['left_sensation'] ?? '') === 'معدوم') {
            $score += 18; $factors[] = ['factor' => 'فقدان الإحساس', 'weight' => 18, 'impact' => 'high'];
        }

        // 4. No pulse / low ABPI
        if (($data['right_pulse'] ?? '') === 'معدوم' || ($data['left_pulse'] ?? '') === 'معدوم') {
            $score += 22; $factors[] = ['factor' => 'انعدام النبض', 'weight' => 22, 'impact' => 'high'];
        }
        $abpi_right = (float)($data['abpi_right'] ?? 1);
        $abpi_left = (float)($data['abpi_left'] ?? 1);
        if ($abpi_right < 0.5 || $abpi_left < 0.5) {
            $score += 20; $factors[] = ['factor' => 'ABPI منخفض جداً', 'weight' => 20, 'impact' => 'critical'];
        } elseif ($abpi_right < 0.9 || $abpi_left < 0.9) {
            $score += 10; $factors[] = ['factor' => 'ABPI منخفض', 'weight' => 10, 'impact' => 'medium'];
        }

        // 5. Smoking
        if (($data['smoking_status'] ?? '') === 'مدخن') { $score += 15; $factors[] = ['factor' => 'تدخين', 'weight' => 15, 'impact' => 'medium']; }

        // 6. Infected wound
        if (in_array($data['wound_condition'] ?? '', ['متسخة', 'صديد'])) { $score += 20; $factors[] = ['factor' => 'جرح ملتهب', 'weight' => 20, 'impact' => 'high']; }

        // 7. Wound size
        $wound_size = (float)($data['wound_size_cm2'] ?? 0);
        if ($wound_size > 10) { $score += 15; $factors[] = ['factor' => 'جرح كبير (>10 سم²)', 'weight' => 15, 'impact' => 'medium']; }
        elseif ($wound_size > 5) { $score += 8; $factors[] = ['factor' => 'جرح متوسط', 'weight' => 8, 'impact' => 'low']; }

        // 8. Comorbidities
        $comorbidity_count = 0;
        foreach (['has_retinopathy', 'has_nephropathy', 'has_neuropathy', 'has_cad', 'has_cva', 'has_pad'] as $c) {
            if (!empty($data[$c])) $comorbidity_count++;
        }
        if ($comorbidity_count >= 3) { $score += 15; $factors[] = ['factor' => 'مضاعفات متعددة', 'weight' => 15, 'impact' => 'medium']; }

        // 9. Visit gap
        $gap = (int)($data['gap_days'] ?? 0);
        if ($gap > 90) { $score += 20; $factors[] = ['factor' => 'منقطع > 90 يوم', 'weight' => 20, 'impact' => 'high']; }
        elseif ($gap > 60) { $score += 10; $factors[] = ['factor' => 'متأخر > 60 يوم', 'weight' => 10, 'impact' => 'medium']; }

        // 10. BMI
        $bmi = (float)($data['bmi'] ?? 0);
        if ($bmi > 35) { $score += 8; $factors[] = ['factor' => 'سمنة مفرطة (BMI>35)', 'weight' => 8, 'impact' => 'low']; }
        elseif ($bmi > 30) { $score += 4; }

        // 11. Diabetes duration
        $duration = (int)($data['duration_years'] ?? 0);
        if ($duration > 20) { $score += 10; $factors[] = ['factor' => 'سكري منذ >20 عام', 'weight' => 10, 'impact' => 'medium']; }
        elseif ($duration > 10) { $score += 5; }

        // 12. LDL
        $ldl = (float)($data['ldl'] ?? 0);
        if ($ldl > 160) { $score += 5; }

        // === PROBABILITY SCORING (logistic-style) ===
        $max_score = 200;
        $probability = round(($score / $max_score) * 100, 1);
        $probability = min($probability, 99);

        // Classification
        if ($score >= 100) { $class = 'حرج جداً'; $color = '#7f1d1d'; }
        elseif ($score >= 70) { $class = 'مرتفع'; $color = '#dc2626'; }
        elseif ($score >= 40) { $class = 'متوسط'; $color = '#f59e0b'; }
        else { $class = 'منخفض'; $color = '#10b981'; }

        // === TIME-TO-EVENT ESTIMATE ===
        $healing_days_estimate = null;
        if ($wagner > 0) {
            $base_days = [0, 30, 60, 120, 200, 300];
            $healing_days_estimate = $base_days[min($wagner, 5)] ?? 150;
            // Adjust for risk factors
            if ($hba1c > 9) $healing_days_estimate += 30;
            if ($gap > 60) $healing_days_estimate += 20;
            if ($wound_size > 10) $healing_days_estimate += 40;
            if (($data['smoking_status'] ?? '') === 'مدخن') $healing_days_estimate += 25;
            if ($comorbidity_count >= 3) $healing_days_estimate += 30;
        }

        // === FORECAST ===
        $forecast = [];
        if ($score >= 80) {
            $forecast['30d'] = '🆘 خطر بتر مرتفع — تدخل فوري مطلوب';
            $forecast['60d'] = '⚠️ مضاعفات خطيرة بدون تدخل';
            $forecast['90d'] = '📋 خطة علاج مكثف ضرورية';
        } elseif ($score >= 50) {
            $forecast['30d'] = '⚠️ مراقبة مكثفة — متابعة كل أسبوع';
            $forecast['60d'] = '📈 قد تتطور القرحة';
            $forecast['90d'] = '✅ تحسن متوقع مع العلاج المناسب';
        } elseif ($score >= 25) {
            $forecast['30d'] = '✅ رعاية منتظمة';
            $forecast['60d'] = '📊 استقرار متوقع';
            $forecast['90d'] = '🩹 التئام متوقع مع الالتزام';
        } else {
            $forecast['30d'] = '🟢 حالة مستقرة';
            $forecast['60d'] = '🟢 متابعة روتينية';
            $forecast['90d'] = '🟢 نتائج إيجابية متوقعة';
        }

        // Adjust for critical factors
        if ($wagner >= 3) {
            $forecast['30d'] = '🆘 خطر بتر — تدخل جراحي محتمل';
            $forecast['60d'] = 'مضاعفات خطيرة بدون تدخل فوري';
        }
        if ($gap > 90) {
            $forecast['30d'] = '⚠️ منقطع — خطر تدهور';
            $forecast['90d'] = '🔴 مضاعفات متقدمة محتملة — استدعاء المريض';
        }

        echo json_encode([
            'score' => $score,
            'max_score' => $max_score,
            'probability' => $probability,
            'class' => $class,
            'color' => $color,
            'factors' => $factors,
            'healing_days_estimate' => $healing_days_estimate,
            'forecast' => $forecast,
            'comorbidity_count' => $comorbidity_count,
            'wagner' => $wagner,
            'hba1c' => $hba1c,
            'gap_days' => $gap,
            'bmi' => $bmi,
            'wound_size' => $wound_size,
            'total_visits' => (int)($data['total_visits'] ?? 0)
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ===================== PATIENT TIMELINE =====================
    case 'patient_timeline':
        if (!$patient_id) { echo json_encode(['error' => 'No patient ID']); exit; }

        $timeline = ['visits' => [], 'hba1c' => [], 'wagner' => [], 'weight' => [], 'treatments' => [], 'labs' => []];

        // Visit dates + vitals
        $result = $mysqli->query("SELECT v.visit_id, v.visit_date, v.visit_reason, v.chief_complaint, v.doctor_notes,
            vs.weight, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
            bs.hba1c_value, bs.fpg_value,
            fa.wagner_grade, fa.right_sensation, fa.left_sensation,
            fu.wound_size_cm2, fu.wound_condition,
            o.improvement_percentage
            FROM visits v
            LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
            LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
            LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
            LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
            LEFT JOIN outcomes o ON v.visit_id = o.visit_id
            WHERE v.patient_id = $patient_id
            ORDER BY v.visit_date ASC");

        while ($row = $result->fetch_assoc()) {
            $timeline['visits'][] = $row;
            if ($row['hba1c_value']) $timeline['hba1c'][] = ['date' => $row['visit_date'], 'value' => (float)$row['hba1c_value']];
            if ($row['fpg_value']) $timeline['hba1c'][] = ['date' => $row['visit_date'], 'value' => (float)$row['fpg_value'], 'is_fpg' => true];
            if ($row['wagner_grade'] !== null) $timeline['wagner'][] = ['date' => $row['visit_date'], 'value' => (int)$row['wagner_grade']];
            if ($row['weight']) $timeline['weight'][] = ['date' => $row['visit_date'], 'value' => (float)$row['weight']];
            if ($row['wound_size_cm2']) $timeline['wound_size'] = $timeline['wound_size'] ?? [];
            $timeline['wound_size'][] = ['date' => $row['visit_date'], 'value' => (float)$row['wound_size_cm2']];
        }

        // Treatments
        $result = $mysqli->query("SELECT t.treatment_type, t.oral_meds_details, t.insulin_details, v.visit_date
            FROM treatments t JOIN visits v ON t.visit_id = v.visit_id WHERE v.patient_id = $patient_id ORDER BY v.visit_date");
        while ($row = $result->fetch_assoc()) $timeline['treatments'][] = $row;

        // Labs
        $result = $mysqli->query("SELECT lr.*, v.visit_date FROM lab_results lr JOIN visits v ON lr.visit_id = v.visit_id WHERE v.patient_id = $patient_id ORDER BY v.visit_date");
        while ($row = $result->fetch_assoc()) $timeline['labs'][] = $row;

        // Patient info
        $patient = $mysqli->query("SELECT p.*, mh.diabetes_type, mh.diagnosis_year, mh.duration_years, mh.smoking_status
            FROM patients p LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id WHERE p.patient_id = $patient_id")->fetch_assoc();

        echo json_encode(['patient' => $patient, 'timeline' => $timeline], JSON_UNESCAPED_UNICODE);
        break;

    // ===================== SIMILAR PATIENTS =====================
    case 'similar_patients':
        if (!$patient_id) { echo json_encode(['error' => 'No patient ID']); exit; }

        // Get source patient's key features
        $source = $mysqli->query("SELECT p.*, mh.diabetes_type, mh.duration_years, mh.smoking_status,
            (SELECT wagner_grade FROM foot_assessments fa2 JOIN visits v2 ON fa2.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as latest_wagner,
            (SELECT hba1c_value FROM blood_sugar_readings bs2 JOIN visits v2 ON bs2.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as latest_hba1c,
            (SELECT COUNT(*) FROM visits v3 WHERE v3.patient_id = p.patient_id) as total_visits,
            (SELECT improvement_percentage FROM outcomes o2 JOIN visits v2 ON o2.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as latest_improvement
            FROM patients p LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id WHERE p.patient_id = $patient_id")->fetch_assoc();

        if (!$source) { echo json_encode(['error' => 'Patient not found']); exit; }

        // Find similar patients using scoring
        $age = (int)$source['age'];
        $wagner = (int)$source['latest_wagner'];
        $hba1c = (float)$source['latest_hba1c'];
        $smoking = $source['smoking_status'];
        $gender = $source['gender'];

        $candidates = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, p.age, p.gender,
            mh.smoking_status,
            (SELECT wagner_grade FROM foot_assessments fa2 JOIN visits v2 ON fa2.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as wagner,
            (SELECT hba1c_value FROM blood_sugar_readings bs2 JOIN visits v2 ON bs2.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as hba1c,
            (SELECT improvement_percentage FROM outcomes o2 JOIN visits v2 ON o2.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as improvement,
            (SELECT COUNT(*) FROM visits v3 WHERE v3.patient_id = p.patient_id) as total_visits,
            (SELECT current_amputation FROM outcomes o3 JOIN visits v2 ON o3.visit_id = v2.visit_id WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as amputation,
            (SELECT DATEDIFF(CURDATE(), MAX(v4.visit_date)) FROM visits v4 WHERE v4.patient_id = p.patient_id) as gap_days
            FROM patients p
            LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
            WHERE p.is_active = 1 AND p.patient_id != $patient_id
            LIMIT 200");

        $similar = [];
        while ($c = $candidates->fetch_assoc()) {
            $similarity = 0;
            $matches = [];

            // Age similarity (±5 years = 10 points)
            $age_diff = abs((int)$c['age'] - $age);
            if ($age_diff <= 5) { $similarity += 10; $matches[] = 'عمر قريب'; }
            elseif ($age_diff <= 10) $similarity += 5;

            // Gender match (5 points)
            if ($c['gender'] === $gender) { $similarity += 5; $matches[] = 'نفس الجنس'; }

            // Wagner similarity (10 points)
            $cw = (int)$c['wagner'];
            $w_diff = abs($cw - $wagner);
            if ($w_diff === 0) { $similarity += 10; $matches[] = 'نفس Wagner'; }
            elseif ($w_diff <= 1) $similarity += 5;

            // HbA1c similarity (10 points)
            $ch = (float)$c['hba1c'];
            if ($ch > 0 && $hba1c > 0) {
                $h_diff = abs($ch - $hba1c);
                if ($h_diff <= 1) { $similarity += 10; $matches[] = 'نفس مستوى HbA1c'; }
                elseif ($h_diff <= 2) $similarity += 5;
            }

            // Smoking match (5 points)
            if ($c['smoking_status'] === $smoking) { $similarity += 5; $matches[] = 'نفس حالة التدخين'; }

            $similar[] = [
                'patient_id' => $c['patient_id'],
                'full_name' => $c['full_name'],
                'file_number' => $c['file_number'],
                'similarity_score' => $similarity,
                'matches' => $matches,
                'wagner' => $cw,
                'hba1c' => $ch,
                'improvement' => $c['improvement'],
                'total_visits' => $c['total_visits'],
                'amputation' => $c['amputation'],
                'gap_days' => $c['gap_days']
            ];
        }

        // Sort by similarity
        usort($similar, fn($a, $b) => $b['similarity_score'] - $a['similarity_score']);
        $top_similar = array_slice($similar, 0, 10);

        // Aggregate treatment recommendations from similar patients
        $similar_ids = array_column($top_similar, 'patient_id');
        $recommendations = [];
        if (!empty($similar_ids)) {
            $ids_str = implode(',', $similar_ids);
            $result = $mysqli->query("SELECT t.treatment_type, COUNT(*) as cnt, AVG(o.improvement_percentage) as avg_improvement
                FROM treatments t JOIN visits v ON t.visit_id = v.visit_id
                LEFT JOIN outcomes o ON v.visit_id = o.visit_id
                WHERE v.patient_id IN ($ids_str) AND t.treatment_type IS NOT NULL AND t.treatment_type != ''
                GROUP BY t.treatment_type ORDER BY cnt DESC LIMIT 3");
            while ($row = $result->fetch_assoc()) $recommendations[] = $row;
        }

        echo json_encode([
            'source' => ['full_name' => $source['full_name'], 'wagner' => $wagner, 'hba1c' => $hba1c, 'age' => $age],
            'similar' => $top_similar,
            'recommendations' => $recommendations
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ===================== ANOMALY DETECTION =====================
    case 'anomalies':
        $anomalies = [];

        // 1. HbA1c sudden spikes (increase >1.5% in 3 months)
        $spikes = $mysqli->query("SELECT v.patient_id, p.full_name, p.file_number, bs1.hba1c_value as older, bs2.hba1c_value as recent,
            v1.visit_date as older_date, v2.visit_date as recent_date,
            (bs2.hba1c_value - bs1.hba1c_value) as spike,
            DATEDIFF(v2.visit_date, v1.visit_date) as days_between
            FROM blood_sugar_readings bs1
            JOIN visits v1 ON bs1.visit_id = v1.visit_id
            JOIN blood_sugar_readings bs2 ON bs2.reading_id > bs1.reading_id AND bs2.visit_id IN (SELECT visit_id FROM visits WHERE patient_id = v1.patient_id)
            JOIN visits v2 ON bs2.visit_id = v2.visit_id
            JOIN patients p ON v1.patient_id = p.patient_id
            WHERE bs2.hba1c_value - bs1.hba1c_value > 1.5
            AND DATEDIFF(v2.visit_date, v1.visit_date) <= 120
            AND DATEDIFF(v2.visit_date, v1.visit_date) >= 30
            GROUP BY v1.patient_id
            ORDER BY spike DESC LIMIT 5");

        while ($s = $spikes->fetch_assoc()) {
            $anomalies[] = [
                'type' => 'hba1c_spike',
                'severity' => 'high',
                'icon' => '📈',
                'title' => 'ارتفاع حاد في HbA1c',
                'patient_name' => $s['full_name'],
                'patient_id' => $s['patient_id'],
                'file_number' => $s['file_number'],
                'detail' => "ارتفاع من {$s['older']}% إلى {$s['recent']}% (زيادة +{$s['spike']}%) في {$s['days_between']} يوم"
            ];
        }

        // 2. Slow healing (wound > 90 days without improvement)
        $slow = $mysqli->query("SELECT v.patient_id, p.full_name, p.file_number, v.visit_date,
            DATEDIFF(CURDATE(), v.visit_date) as days_open,
            fu.wound_size_cm2, fa.wagner_grade
            FROM visits v
            JOIN patients p ON v.patient_id = p.patient_id
            JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
            JOIN foot_assessments fa ON v.visit_id = fa.visit_id
            LEFT JOIN outcomes o ON v.visit_id = o.visit_id
            WHERE (o.improvement_percentage IS NULL OR o.improvement_percentage < 50)
            AND v.visit_date <= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            ORDER BY days_open DESC LIMIT 5");

        while ($s = $slow->fetch_assoc()) {
            $anomalies[] = [
                'type' => 'slow_healing',
                'severity' => 'critical',
                'icon' => '🩹',
                'title' => 'تأخر في الالتئام',
                'patient_name' => $s['full_name'],
                'patient_id' => $s['patient_id'],
                'file_number' => $s['file_number'],
                'detail' => "الجرح مفتوح منذ {$s['days_open']} يوم (Wagner {$s['wagner_grade']})"
            ];
        }

        // 3. Wagner worsening (any patient whose Wagner increased)
        $worsening = $mysqli->query("SELECT v.patient_id, p.full_name, p.file_number,
            fa1.wagner_grade as grade_from, fa2.wagner_grade as grade_to,
            v1.visit_date as date_from, v2.visit_date as date_to
            FROM foot_assessments fa1
            JOIN visits v1 ON fa1.visit_id = v1.visit_id
            JOIN foot_assessments fa2 ON fa2.assessment_id > fa1.assessment_id 
                AND fa2.visit_id IN (SELECT visit_id FROM visits WHERE patient_id = v1.patient_id)
            JOIN visits v2 ON fa2.visit_id = v2.visit_id
            JOIN patients p ON v1.patient_id = p.patient_id
            WHERE fa2.wagner_grade > fa1.wagner_grade
            ORDER BY (fa2.wagner_grade - fa1.wagner_grade) DESC LIMIT 5");

        while ($s = $worsening->fetch_assoc()) {
            $anomalies[] = [
                'type' => 'wagner_worsening',
                'severity' => 'critical',
                'icon' => '⚠️',
                'title' => 'تدهور درجة Wagner',
                'patient_name' => $s['full_name'],
                'patient_id' => $s['patient_id'],
                'file_number' => $s['file_number'],
                'detail' => "من Wagner {$s['grade_from']} إلى {$s['grade_to']}"
            ];
        }

        // 4. Treatment non-response (HbA1c >9% after 6 months on same treatment)
        $non_response = $mysqli->query("SELECT v.patient_id, p.full_name, p.file_number,
            bs.hba1c_value, t.treatment_type,
            (SELECT MIN(v3.visit_date) FROM visits v3 WHERE v3.patient_id = v.patient_id) as first_treatment_date
            FROM visits v
            JOIN patients p ON v.patient_id = p.patient_id
            JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
            JOIN treatments t ON v.visit_id = t.visit_id
            WHERE bs.hba1c_value > 9
            AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            AND v.visit_date >= (SELECT MIN(v2.visit_date) FROM visits v2 WHERE v2.patient_id = v.patient_id) + INTERVAL 6 MONTH
            GROUP BY v.patient_id
            ORDER BY bs.hba1c_value DESC LIMIT 5");

        while ($s = $non_response->fetch_assoc()) {
            $anomalies[] = [
                'type' => 'non_response',
                'severity' => 'high',
                'icon' => '💊',
                'title' => 'عدم استجابة للعلاج',
                'patient_name' => $s['full_name'],
                'patient_id' => $s['patient_id'],
                'file_number' => $s['file_number'],
                'detail' => "HbA1c {$s['hba1c_value']}% رغم العلاج ({$s['treatment_type']}) لأكثر من 6 أشهر"
            ];
        }

        echo json_encode($anomalies, JSON_UNESCAPED_UNICODE);
        break;

    // ===================== AUTOMATED INSIGHTS =====================
    case 'insights':
        $insights = [];

        // Insight 1: HbA1c improvement rate quarter over quarter
        $q_hba1c = $mysqli->query("SELECT 
            (SELECT AVG(bs.hba1c_value) FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) as current_q,
            (SELECT AVG(bs.hba1c_value) FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE v.visit_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND DATE_SUB(CURDATE(), INTERVAL 3 MONTH)) as prev_q")->fetch_assoc();

        if ($q_hba1c['current_q'] && $q_hba1c['prev_q']) {
            $change = round((float)$q_hba1c['prev_q'] - (float)$q_hba1c['current_q'], 1);
            if ($change > 0.3) $insights[] = ['type' => 'improvement', 'icon' => '📈', 'text' => "تحسن متوسط HbA1c بمقدار {$change}% مقارنة بالربع الماضي", 'trend' => 'up', 'metric' => 'hba1c'];
            elseif ($change < -0.3) $insights[] = ['type' => 'warning', 'icon' => '📉', 'text' => "ارتفاع متوسط HbA1c بمقدار " . abs($change) . "% مقارنة بالربع الماضي", 'trend' => 'down', 'metric' => 'hba1c'];
        }

        // Insight 2: High risk patients count
        $high_risk = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3")->fetch_assoc()['cnt'];
        if ($high_risk > 0) $insights[] = ['type' => 'alert', 'icon' => '⚠️', 'text' => "{$high_risk} مريض بجروح حرجة (Wagner 3+) يحتاجون تدخل عاجل", 'trend' => 'neutral', 'metric' => 'high_risk'];

        // Insight 3: Dropout rate
        $dropout = $mysqli->query("SELECT ROUND(COUNT(DISTINCT CASE WHEN (SELECT COUNT(*) FROM visits v2 WHERE v2.patient_id = p.patient_id) = 1 THEN p.patient_id END) / NULLIF(COUNT(DISTINCT p.patient_id), 0) * 100, 1) as rate FROM patients p WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)")->fetch_assoc()['rate'];
        if ($dropout > 30) $insights[] = ['type' => 'warning', 'icon' => '🚪', 'text' => "نسبة تسرب مرضى جدد {$dropout}% — أعلى من المستوى المقبول", 'trend' => 'down', 'metric' => 'dropout'];

        // Insight 4: Amputation rate
        $amp_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes WHERE current_amputation IS NOT NULL AND current_amputation != 'لا' AND current_amputation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)")->fetch_assoc()['cnt'];
        if ($amp_count > 0) $insights[] = ['type' => 'alert', 'icon' => '🦶', 'text' => "تم تسجيل {$amp_count} حالة بتر خلال الـ12 شهراً الماضية", 'trend' => 'neutral', 'metric' => 'amputation'];

        // Insight 5: Healing rate
        $heal = $mysqli->query("SELECT ROUND(COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate FROM visits v LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id LEFT JOIN outcomes o ON v.visit_id = o.visit_id WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)")->fetch_assoc()['rate'];
        if ($heal > 0) $insights[] = ['type' => 'improvement', 'icon' => '✅', 'text' => "معدل الشفاء {$heal}% خلال آخر 6 أشهر", 'trend' => 'up', 'metric' => 'healing'];

        // Insight 6: Gender comparison
        $male_hba1c = $mysqli->query("SELECT AVG(bs.hba1c_value) as avg FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id JOIN patients p ON v.patient_id = p.patient_id WHERE p.gender = 'ذكر' AND bs.hba1c_value IS NOT NULL")->fetch_assoc()['avg'];
        $female_hba1c = $mysqli->query("SELECT AVG(bs.hba1c_value) as avg FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id JOIN patients p ON v.patient_id = p.patient_id WHERE p.gender = 'أنثى' AND bs.hba1c_value IS NOT NULL")->fetch_assoc()['avg'];
        if ($male_hba1c && $female_hba1c && abs($male_hba1c - $female_hba1c) > 0.3) {
            $better = $male_hba1c < $female_hba1c ? 'الذكور' : 'الإناث';
            $worse_gender = $male_hba1c < $female_hba1c ? 'الإناث' : 'الذكور';
            $diff = round(abs($male_hba1c - $female_hba1c), 1);
            $insights[] = ['type' => 'info', 'icon' => '👤', 'text' => "التحكم في HbA1c أفضل لدى {$better} ({$diff}% فرق مع {$worse_gender})", 'trend' => 'neutral', 'metric' => 'gender'];
        }

        // Insight 7: Smoking + foot ulcer comorbidity
        $smoke_ulcer = $mysqli->query("SELECT COUNT(DISTINCT p.patient_id) as cnt FROM patients p JOIN medical_history mh ON p.patient_id = mh.patient_id JOIN visits v ON p.patient_id = v.patient_id JOIN foot_ulcers fu ON v.visit_id = fu.visit_id WHERE mh.smoking_status = 'مدخن'")->fetch_assoc()['cnt'];
        if ($smoke_ulcer > 0) $insights[] = ['type' => 'alert', 'icon' => '🚬', 'text' => "{$smoke_ulcer} مريض مدخن يعانون من قرحة قدم السكري", 'trend' => 'down', 'metric' => 'smoking_ulcer'];

        // Insight 8: Visit frequency impact
        $freq_impact = $mysqli->query("SELECT 
            ROUND(AVG(CASE WHEN vc.visit_count >= 6 THEN o2.improvement_percentage END), 1) as high_freq_improvement,
            ROUND(AVG(CASE WHEN vc.visit_count <= 2 THEN o2.improvement_percentage END), 1) as low_freq_improvement
            FROM (SELECT v.patient_id, COUNT(*) as visit_count FROM visits v WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY v.patient_id) vc
            JOIN visits v2 ON vc.patient_id = v2.patient_id
            JOIN outcomes o2 ON v2.visit_id = o2.visit_id
            WHERE o2.improvement_percentage IS NOT NULL")->fetch_assoc();

        if ($freq_impact['high_freq_improvement'] && $freq_impact['low_freq_improvement']) {
            $diff = round((float)$freq_impact['high_freq_improvement'] - (float)$freq_impact['low_freq_improvement'], 1);
            if ($diff > 10) $insights[] = ['type' => 'info', 'icon' => '📅', 'text' => "المرضى المنتظمون (≥6 زيارات/سنة) يحققون تحسناً أكثر بنسبة {$diff}% من غير المنتظمين", 'trend' => 'up', 'metric' => 'frequency'];
        }

        echo json_encode($insights, JSON_UNESCAPED_UNICODE);
        break;

    // ===================== WHAT-IF SIMULATOR =====================
    case 'what_if':
        if (!$patient_id) { echo json_encode(['error' => 'No patient ID']); exit; }

        $current_data = $mysqli->query("SELECT p.*,
            (SELECT hba1c_value FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE v.patient_id = p.patient_id ORDER BY v.visit_date DESC LIMIT 1) as hba1c,
            (SELECT wagner_grade FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id WHERE v.patient_id = p.patient_id ORDER BY v.visit_date DESC LIMIT 1) as wagner,
            (SELECT smoking_status FROM medical_history WHERE patient_id = p.patient_id LIMIT 1) as smoking
            FROM patients p WHERE p.patient_id = $patient_id")->fetch_assoc();

        $hba1c = (float)($current_data['hba1c'] ?? 7);
        $wagner = (int)($current_data['wagner'] ?? 0);
        $smoking = $current_data['smoking'] ?? 'لا';

        $scenarios = [];

        // Scenario 1: HbA1c drop
        $targets = [7, 6.5];
        foreach ($targets as $target) {
            if ($hba1c > $target) {
                $current_risk = min(round(($hba1c / 14) * 100, 1), 99);
                $new_risk = min(round(($target / 14) * 100, 1), 99);
                $reduction = round($current_risk - $new_risk, 1);
                $days_saved = round(($hba1c - $target) * 15);
                $scenarios[] = [
                    'name' => "خفض HbA1c إلى {$target}%",
                    'current' => $hba1c,
                    'target' => $target,
                    'current_risk' => $current_risk,
                    'new_risk' => $new_risk,
                    'risk_reduction' => $reduction,
                    'days_saved' => max($days_saved, 0),
                    'detail' => "انخفاض درجة الخطورة بمقدار {$reduction} نقطة"
                ];
            }
        }

        // Scenario 2: Quit smoking
        if ($smoking === 'مدخن') {
            $scenarios[] = [
                'name' => 'الإقلاع عن التدخين',
                'current' => 'مدخن',
                'target' => 'غير مدخن',
                'current_risk' => min(round(($hba1c / 14) * 100 + 15, 1), 99),
                'new_risk' => min(round(($hba1c / 14) * 100, 1), 99),
                'risk_reduction' => 15,
                'days_saved' => 25,
                'detail' => 'انخفاض درجة الخطورة بمقدار 15 نقطة'
            ];
        }

        // Scenario 3: Regular visits (no gap)
        $gap = $mysqli->query("SELECT DATEDIFF(CURDATE(), MAX(visit_date)) as gap FROM visits WHERE patient_id = $patient_id")->fetch_assoc()['gap'];
        if ($gap > 60) {
            $scenarios[] = [
                'name' => 'المتابعة المنتظمة',
                'current' => "منذ {$gap} يوم",
                'target' => 'زيارة كل 30 يوم',
                'current_risk' => min(round(($hba1c / 14) * 100 + 10, 1), 99),
                'new_risk' => min(round(($hba1c / 14) * 100, 1), 99),
                'risk_reduction' => 10,
                'days_saved' => 15,
                'detail' => "المريض منقطع منذ {$gap} يوم — المتابعة المنتظمة تحسن النتائج"
            ];
        }

        echo json_encode([
            'patient_name' => $current_data['full_name'],
            'current_hba1c' => $hba1c,
            'current_wagner' => $wagner,
            'scenarios' => $scenarios
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ===================== STANDARD STATISTICS =====================
    case 'statistics':
        $stats = [];
        $result = $mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1");
        $stats['total_patients'] = (int)$result->fetch_assoc()['c'];
        $result = $mysqli->query("SELECT COUNT(*) as c FROM visits");
        $stats['total_visits'] = (int)$result->fetch_assoc()['c'];
        $result = $mysqli->query("SELECT COUNT(*) as c FROM foot_assessments");
        $stats['total_assessments'] = (int)$result->fetch_assoc()['c'];
        $result = $mysqli->query("SELECT COUNT(*) as c FROM foot_ulcers");
        $stats['total_ulcers'] = (int)$result->fetch_assoc()['c'];
        $result = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN outcomes o ON v.visit_id = o.visit_id WHERE o.improvement_percentage >= 100");
        $stats['healed'] = (int)$result->fetch_assoc()['c'];
        $result = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3");
        $stats['high_risk'] = (int)$result->fetch_assoc()['c'];
        $result = $mysqli->query("SELECT AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_days FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id WHERE o.healing_date IS NOT NULL");
        $avg_row = $result->fetch_assoc();
        $stats['avg_healing_days'] = $avg_row['avg_days'] ? round((float)$avg_row['avg_days']) : null;
        echo json_encode($stats, JSON_UNESCAPED_UNICODE);
        break;

    case 'demographics':
        $demo = [];
        $demo['gender'] = [
            'ذكر' => (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'ذكر' AND is_active = 1")->fetch_assoc()['c'],
            'أنثى' => (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE gender = 'أنثى' AND is_active = 1")->fetch_assoc()['c']
        ];
        $age_groups = [];
        $result = $mysqli->query("SELECT CASE WHEN age < 18 THEN '<18' WHEN age BETWEEN 18 AND 30 THEN '18-30' WHEN age BETWEEN 31 AND 45 THEN '31-45' WHEN age BETWEEN 46 AND 60 THEN '46-60' WHEN age BETWEEN 61 AND 75 THEN '61-75' ELSE '75+' END as grp, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY grp");
        while ($row = $result->fetch_assoc()) { $age_groups[] = $row; }
        $demo['age_groups'] = $age_groups;
        $cities = [];
        $result = $mysqli->query("SELECT COALESCE(city, 'غير محدد') as city, COUNT(*) as cnt FROM patients WHERE is_active = 1 GROUP BY city ORDER BY cnt DESC LIMIT 8");
        while ($row = $result->fetch_assoc()) { $cities[] = $row; }
        $demo['cities'] = $cities;
        echo json_encode($demo, JSON_UNESCAPED_UNICODE);
        break;

    case 'monthly_trends':
        $trends = [];
        $result = $mysqli->query("SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as cnt FROM visits GROUP BY DATE_FORMAT(visit_date, '%Y-%m') ORDER BY month LIMIT 12");
        while ($row = $result->fetch_assoc()) { $trends[] = $row; }
        echo json_encode($trends, JSON_UNESCAPED_UNICODE);
        break;

    case 'treatment_efficacy':
        $efficacy = [];
        $result = $mysqli->query("SELECT t.treatment_type, AVG(o.improvement_percentage) as avg_improvement, COUNT(*) as total FROM treatments t JOIN visits v ON t.visit_id = v.visit_id JOIN outcomes o ON v.visit_id = o.visit_id WHERE t.treatment_type IS NOT NULL AND t.treatment_type != '' GROUP BY t.treatment_type ORDER BY avg_improvement DESC");
        while ($row = $result->fetch_assoc()) { $efficacy[] = $row; }
        echo json_encode($efficacy, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>