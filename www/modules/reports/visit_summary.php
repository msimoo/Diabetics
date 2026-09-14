<?php
/**
 * Visit Summary Report Module
 * Prints a comprehensive visit summary for a specific visit
 */
$page_title = 'تقرير الزيارة | Visit Summary';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if (!$visit_id) {
    header('Location: ' . BASE_URL . '/modules/patients/index.php?error=missing_visit');
    exit;
}

// Fetch visit with patient data
$stmt = $mysqli->prepare("
    SELECT v.*, p.full_name, p.file_number, p.date_of_birth, p.gender, p.phone_primary, p.city, p.nationality,
           TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age,
           u.full_name AS doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    LEFT JOIN users u ON v.created_by = u.user_id
    WHERE v.visit_id = ? AND p.is_active = 1
");
$stmt->bind_param('i', $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    header('Location: ' . BASE_URL . '/modules/patients/index.php?error=visit_not_found');
    exit;
}

// Fetch vital signs
$vitals_stmt = $mysqli->prepare("SELECT * FROM vital_signs WHERE visit_id = ?");
$vitals_stmt->bind_param('i', $visit_id);
$vitals_stmt->execute();
$vitals = $vitals_stmt->get_result()->fetch_assoc();

// Fetch blood sugar readings
$bs_stmt = $mysqli->prepare("SELECT * FROM blood_sugar_readings WHERE visit_id = ?");
$bs_stmt->bind_param('i', $visit_id);
$bs_stmt->execute();
$blood_sugar = $bs_stmt->get_result()->fetch_assoc();

// Fetch treatments
$treat_stmt = $mysqli->prepare("SELECT * FROM treatments WHERE visit_id = ?");
$treat_stmt->bind_param('i', $visit_id);
$treat_stmt->execute();
$treatments = $treat_stmt->get_result()->fetch_assoc();

// Fetch complications (by patient)
$comp_stmt = $mysqli->prepare("SELECT * FROM complications WHERE patient_id = (SELECT patient_id FROM visits WHERE visit_id = ?)");
$comp_stmt->bind_param('i', $visit_id);
$comp_stmt->execute();
$complications = $comp_stmt->get_result()->fetch_assoc();

// Fetch foot assessment
$foot_stmt = $mysqli->prepare("SELECT * FROM foot_assessments WHERE visit_id = ?");
$foot_stmt->bind_param('i', $visit_id);
$foot_stmt->execute();
$foot = $foot_stmt->get_result()->fetch_assoc();

// Fetch lab results
$lab_stmt = $mysqli->prepare("SELECT * FROM lab_results WHERE visit_id = ?");
$lab_stmt->bind_param('i', $visit_id);
$lab_stmt->execute();
$labs = $lab_stmt->get_result()->fetch_assoc();

// Fetch care plan
$care_stmt = $mysqli->prepare("SELECT * FROM care_plan WHERE visit_id = ?");
$care_stmt->bind_param('i', $visit_id);
$care_stmt->execute();
$care_plan = $care_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_output($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/print.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/animations.css">
    <style>
        .report-container { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .report-header {
            text-align: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .report-header h1 { font-size: 1.8rem; color: var(--primary); margin-bottom: 0.3rem; }
        .report-header .clinic-name { font-size: 1.2rem; color: var(--gold); }
        .report-header .report-date { font-size: 0.9rem; color: #888; margin-top: 0.5rem; }
        .patient-badge {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.5rem;
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .patient-badge span { font-size: 0.9rem; }
        .patient-badge strong { color: var(--primary); }

        .report-section {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .report-section-title {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1rem;
            font-size: 1rem;
            font-weight: 600;
        }
        .report-section-body { padding: 1rem; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table td, .report-table th {
            border: 1px solid #e0e0e0;
            padding: 0.5rem 0.8rem;
            text-align: right;
            font-size: 0.9rem;
        }
        .report-table th { background: #f5f5f5; font-weight: 600; width: 35%; }
        .report-table td { width: 65%; }

        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px dashed #ccc;
        }
        .signature-box { text-align: center; }
        .signature-box .line {
            height: 1px;
            border-top: 2px solid #333;
            margin: 2rem 0 0.3rem;
            width: 200px;
            display: inline-block;
        }
        .signature-box p { font-size: 0.85rem; color: #555; }

        .diagnosis-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .diagnosis-badge.good { background: #e8f5e9; color: #2e7d32; }
        .diagnosis-badge.warning { background: #fff3e0; color: #e65100; }
        .diagnosis-badge.danger { background: #ffebee; color: #c62828; }

        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .report-section { break-inside: avoid; }
        }

        .print-controls {
            text-align: center;
            margin: 2rem 0;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        @media print {
            .print-controls { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
    </div>

    <div class="report-container">
        <!-- Print Controls -->
        <div class="print-controls">
            <button onclick="window.print()" class="btn btn-primary">🖨️ طباعة التقرير</button>
            <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $visit['patient_id']; ?>" class="btn btn-secondary">العودة للمريض</a>
        </div>

        <!-- Report Header -->
        <div class="report-header fade-in">
            <h1><?php echo SITE_NAME_EN; ?></h1>
            <div class="clinic-name">تقرير زيارة طبية</div>
            <div class="report-date">
                تاريخ التقرير: <?= date('Y-m-d h:i A') ?> | 
                رقم الزيارة: #<?php echo $visit['visit_id']; ?>
            </div>
        </div>

        <!-- Patient Info -->
        <div class="patient-badge fade-in">
            <span><strong>👤 الاسم:</strong> <?= escape_output($visit['full_name']) ?></span>
            <span><strong>📁 رقم الملف:</strong> <?= escape_output($visit['file_number']) ?></span>
            <span><strong>🎂 العمر:</strong> <?= $visit['age'] ?> سنة</span>
            <span><strong>⚥ الجنس:</strong> <?php echo $visit['gender'] === 'ذكر' ? 'ذكر' : 'أنثى'; ?></span>
            <span><strong>📞 الهاتف:</strong> <?= escape_output($visit['phone_primary']) ?></span>
            <span><strong>🏙️ المدينة:</strong> <?= escape_output($visit['city']) ?></span>
            <span><strong>📅 تاريخ الزيارة:</strong> <?= $visit['visit_date'] ?? date('Y-m-d') ?></span>
            <span><strong>👨‍⚕️ الطبيب:</strong> <?= escape_output($visit['doctor_name'] ?? 'غير محدد') ?></span>
        </div>

        <!-- Vital Signs -->
        <?php if ($vitals): ?>
        <div class="report-section fade-in">
            <div class="report-section-title">📊 العلامات الحيوية</div>
            <div class="report-section-body">
                <table class="report-table">
                    <tr><th>الوزن (كجم)</th><td><?= escape_output($vitals['weight'] ?? '—') ?></td></tr>
                    <tr><th>الطول (سم)</th><td><?= escape_output($vitals['height'] ?? '—') ?></td></tr>
                    <tr><th>مؤشر كتلة الجسم (BMI)</th><td><?= escape_output(number_format($vitals['bmi'], 1)) ?? '—' ?></td></tr>
                    <tr><th>ضغط الدم</th><td><?= escape_output($vitals['blood_pressure_text'] ?? '—') ?></td></tr>
                    <tr><th>محيط الخصر (سم)</th><td><?= escape_output($vitals['waist_circumference'] ?? '—') ?></td></tr>
                    <tr><th>درجة الحرارة (°م)</th><td><?= escape_output($vitals['temperature'] ?? '—') ?></td></tr>
                    <tr><th>معدل النبض (ن/د)</th><td><?= escape_output($vitals['heart_rate'] ?? '—') ?></td></tr>
                    <tr><th>تشبع الأكسجين (%)</th><td><?= escape_output($vitals['oxygen_saturation'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Blood Sugar -->
        <?php if ($blood_sugar): ?>
        <div class="report-section fade-in">
            <div class="report-section-title">🩸 تحاليل السكر</div>
            <div class="report-section-body">
                <table class="report-table">
                    <tr><th>سكر الصائم (FPG)</th><td><?= escape_output($blood_sugar['fpg_value'] ?? '—') ?> ملجم/ديسيلتر</td></tr>
                    <tr><th>تاريخ سكر الصائم</th><td><?= escape_output($blood_sugar['fpg_date'] ?? '—') ?></td></tr>
                    <tr><th>سكر بعد الأكل (PPG)</th><td><?= escape_output($blood_sugar['ppg_value'] ?? '—') ?> ملجم/ديسيلتر</td></tr>
                    <tr><th>تاريخ سكر بعد الأكل</th><td><?= escape_output($blood_sugar['ppg_date'] ?? '—') ?></td></tr>
                    <tr><th>الهيموجلوبين السكري (HbA1c)</th>
                        <td>
                            <?= escape_output($blood_sugar['hba1c_value'] ?? '—') ?>%
                            <?php if (isset($blood_sugar['hba1c_value']) && $blood_sugar['hba1c_value'] > 7): ?>
                                <span class="diagnosis-badge danger">مرتفع</span>
                            <?php elseif (isset($blood_sugar['hba1c_value']) && $blood_sugar['hba1c_value'] >= 6.5): ?>
                                <span class="diagnosis-badge warning">حدودي</span>
                            <?php elseif (isset($blood_sugar['hba1c_value'])): ?>
                                <span class="diagnosis-badge good">ممتاز</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><th>تاريخ HbA1c</th><td><?= escape_output($blood_sugar['hba1c_date'] ?? '—') ?></td></tr>
                    <tr><th>سكر عشوائي</th><td><?= escape_output($blood_sugar['random_sugar_value'] ?? '—') ?> ملجم/ديسيلتر</td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Complications -->
        <?php if ($complications): ?>
        <div class="report-section fade-in">
            <div class="report-section-title">⚠️ المضاعفات</div>
            <div class="report-section-body">
                <table class="report-table">
                    <thead>
                        <tr><th>نوع المضاعفة</th><th>الحالة</th><th>ملاحظات</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $comp_list = [
                            'has_retinopathy' => 'اعتلال الشبكية',
                            'has_nephropathy' => 'اعتلال الكلى',
                            'has_neuropathy' => 'اعتلال الأعصاب',
                            'has_cad' => 'أمراض القلب التاجية',
                            'has_cva' => 'الأوعية الدماغية',
                            'has_pad' => 'الأوعية الطرفية'
                        ];
                        foreach ($comp_list as $field => $label): 
                            if (!empty($complications[$field])): ?>
                        <tr>
                            <td><?php echo $label; ?></td>
                            <td>نشط</td>
                            <td><?php echo escape_output($complications['notes'] ?? '—'); ?></td>
                        </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Foot Assessment -->
        <?php if ($foot): ?>
        <div class="report-section fade-in">
            <div class="report-section-title">🦶 فحص القدم</div>
            <div class="report-section-body">
                <table class="report-table">
                    <tr><th>القدم اليمنى - الإحساس</th><td><?php echo escape_output($foot['right_sensation'] ?? '—'); ?></td></tr>
                    <tr><th>القدم اليمنى - النبض</th><td><?php echo escape_output($foot['right_pulse'] ?? '—'); ?></td></tr>
                    <tr><th>القدم اليسرى - الإحساس</th><td><?php echo escape_output($foot['left_sensation'] ?? '—'); ?></td></tr>
                    <tr><th>القدم اليسرى - النبض</th><td><?php echo escape_output($foot['left_pulse'] ?? '—'); ?></td></tr>
                    <tr><th>ABPI الأيمن</th><td><?php echo escape_output($foot['abpi_right'] ?? '—'); ?></td></tr>
                    <tr><th>ABPI الأيسر</th><td><?php echo escape_output($foot['abpi_left'] ?? '—'); ?></td></tr>
                    <tr><th>درجة Wagner</th><td><?php echo escape_output($foot['wagner_grade'] ?? '—'); ?></td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Treatments -->
        <?php if ($treatments): ?>
        <div class="report-section fade-in">
            <div class="report-section-title">💊 العلاجات</div>
            <div class="report-section-body">
                <table class="report-table">
                    <thead>
                        <tr><th>العلاج</th><th>الجرعة</th><th>طريقة / تكرار</th><th>ملاحظات</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo escape_output($treatments['treatment_type'] ?? '—'); ?></td>
                            <td><?php echo escape_output($treatments['oral_meds_details'] ?? '—'); ?></td>
                            <td><?php echo escape_output($treatments['insulin_details'] ?? '—'); ?></td>
                            <td><?php echo escape_output($treatments['other_meds'] ?? '—'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Care Plan -->
        <?php if ($care_plan): ?>
        <div class="report-section fade-in">
            <div class="report-section-title">📋 خطة الرعاية</div>
            <div class="report-section-body">
                <table class="report-table">
                    <tr><th>نوع العناية</th><td><?php echo escape_output($care_plan['care_type'] ?? '—'); ?></td></tr>
                    <tr><th>عدد مرات الغيار</th><td><?php echo escape_output($care_plan['dressing_frequency'] ?? '—'); ?></td></tr>
                    <tr><th>تخفيف الضغط</th><td><?php echo $care_plan['offloading_needed'] ? 'مطلوب' : 'غير مطلوب'; ?></td></tr>
                    <tr><th>الخطة الغذائية</th><td><?php echo nl2br(escape_output($care_plan['dietary_plan'] ?? '—')); ?></td></tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Notes -->
        <div class="report-section fade-in">
            <div class="report-section-title">📝 ملخص الزيارة</div>
            <div class="report-section-body">
                <p style="line-height: 1.8; color: #444;">
                    تمت زيارة المريض <?= escape_output($visit['full_name']) ?> بتاريخ <?= $visit['visit_date'] ?? date('Y-m-d') ?>.
                    <?php if ($blood_sugar && isset($blood_sugar['hba1c_value'])): ?>
                        نسبة HbA1c: <?= $blood_sugar['hba1c_value'] ?>%.
                    <?php endif; ?>
                    <?php if ($foot && isset($foot['wagner_grade'])): ?>
                        درجة Wagner: <?php echo $foot['wagner_grade']; ?>.
                    <?php endif; ?>
                    <?php if ($care_plan && isset($care_plan['follow_up_date'])): ?>
                        موعد المتابعة القادم: <?= $care_plan['follow_up_date'] ?>.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signature-section fade-in">
            <div class="signature-box">
                <div class="line"></div>
                <p>توقيع الطبيب المعالج</p>
                <p style="font-size:0.85rem; color:#888;"><?= escape_output($visit['doctor_name'] ?? '') ?></p>
            </div>
            <div class="signature-box">
                <div class="line"></div>
                <p>توقيع المريض أو ذويه</p>
                <p style="font-size:0.85rem; color:#888;"><?= escape_output($visit['full_name']) ?></p>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 2rem; font-size: 0.8rem; color: #aaa; border-top: 1px solid #eee; padding-top: 1rem;">
            <?php echo SITE_NAME; ?> — جميع الحقوق محفوظة © <?php echo date('Y'); ?>
            <br>
            تم إنشاء التقرير بتاريخ <?= date('Y-m-d h:i A') ?>
        </div>
    </div>

    <div class="no-print">
            </div>
        </main>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
