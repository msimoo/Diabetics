<?php
/**
 * ai_client.php — عميل الاتصال بمحرك الذكاء الاصطناعي (Python Flask API)
 * AI Client: sends patient data to Python ML models and returns predictions
 * 
 * الاستخدام:
 *   $result = callAI('predict/risk', $patient_data);
 *   if ($result['success']) {
 *       echo $result['data']['risk_score'];
 *   }
 */

/**
 * إرسال بيانات المريض إلى خادم AI للحصول على توقعات
 * 
 * @param string $endpoint  نقطة النهاية (predict/risk, predict/healing, recommend, analyze)
 * @param array  $data      بيانات المريض
 * @param int    $timeout   مهلة الاتصال بالثواني
 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
 */
function callAI(string $endpoint, array $data, int $timeout = 10): array {
    $ai_host = defined('AI_HOST') ? AI_HOST : 'http://localhost:5000';
    $url = rtrim($ai_host, '/') . '/api/' . ltrim($endpoint, '/');
    
    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json_data === false) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'فشل تحويل البيانات إلى JSON: ' . json_last_error_msg()
        ];
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json; charset=utf-8',
            'Content-Length: ' . strlen($json_data),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FAILONERROR => false,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $curl_error) {
        $error_msg = str_contains($curl_error, 'Connection refused')
            ? 'خادم AI غير قيد التشغيل. قم بتشغيل: python ai_api/app.py'
            : (str_contains($curl_error, 'timed out')
                ? 'خادم AI لا يستجيب (مهلة الاتصال).'
                : 'خطأ في الاتصال: ' . $curl_error);
        return ['success' => false, 'data' => null, 'error' => $error_msg];
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'data' => null, 'error' => 'استجابة غير صالحة'];
    }
    
    if ($http_code >= 400 || isset($result['error'])) {
        return ['success' => false, 'data' => $result, 'error' => $result['error'] ?? 'خطأ (HTTP ' . $http_code . ')'];
    }
    
    return ['success' => true, 'data' => $result, 'error' => null];
}

/**
 * تحليل مريض كامل (جميع النماذج)
 * يستخدم subquery للحصول على أحدث زيارة فقط لكل مريض (تجنب GROUP BY + ORDER BY inconsistency)
 */
function analyzePatient(int $patient_id): array {
    global $mysqli;
    
    // الخطوة 1: الحصول على أحدث visit_id للمريض
    $latest_visit = $mysqli->prepare(
        "SELECT visit_id FROM visits WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 1"
    );
    $latest_visit->bind_param('i', $patient_id);
    $latest_visit->execute();
    $lv_result = $latest_visit->get_result();
    $lv = $lv_result->fetch_assoc();
    $visit_id = $lv ? (int)$lv['visit_id'] : 0;
    
    // الخطوة 2: جلب بيانات المريض + أحدث البيانات السريرية
    $query = "SELECT 
        p.patient_id, p.full_name, p.age, p.gender, p.city,
        mh.diabetes_type, mh.duration_years, mh.smoking_status, mh.physical_activity,
        vs.weight, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
        bs.hba1c_value, bs.fpg_value,
        fa.wagner_grade, fa.right_sensation, fa.left_sensation,
        fa.right_pulse, fa.left_pulse, fa.abpi_right, fa.abpi_left,
        fu.wound_condition, fu.wound_depth, fu.wound_size_cm2, fu.initial_cause,
        o.improvement_percentage, o.current_amputation,
        DATEDIFF(CURDATE(), (
            SELECT MAX(v2.visit_date) FROM visits v2 WHERE v2.patient_id = p.patient_id
        )) as days_since_last_visit,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as total_visits
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON v.visit_id = ?
    LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.patient_id = ?";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return ['success' => false, 'error' => 'خطأ في قاعدة البيانات: ' . $mysqli->error, 'data' => null];
    }
    $stmt->bind_param('ii', $visit_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();
    
    if (!$patient_data) {
        return ['success' => false, 'error' => 'المريض غير موجود', 'data' => null];
    }
    
    return callAI('analyze', $patient_data);
}

/**
 * فحص حالة خادم AI
 */
function checkAIHealth(): array {
    $result = callAI('health', []);
    $data = $result['data'] ?? [];
    $risk_loaded = $data['risk_loaded'] ?? false;
    $healing_loaded = $data['healing_loaded'] ?? false;
    return [
        'running' => $result['success'],
        'risk_loaded' => $risk_loaded,
        'healing_loaded' => $healing_loaded,
        'models_loaded' => $risk_loaded,  // backward compat
        'message' => $result['success']
            ? 'خادم AI يعمل'
            : ($result['error'] ?? 'خادم AI غير متاح'),
    ];
}
?>