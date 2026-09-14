<?php
/**
 * Patient Full Report Module
 * Comprehensive printable report for a patient with all visit history
 */
$page_title = 'تقرير المريض الشامل | Patient Report';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header('Location: ' . BASE_URL . '/modules/patients/index.php?error=missing_patient');
    exit;
}

// Fetch patient
$stmt = $mysqli->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM patients WHERE patient_id = ? AND is_active = 1");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header('Location: ' . BASE_URL . '/modules/patients/index.php?error=patient_not_found');
    exit;
}

// Fetch all visits with data
$visits_stmt = $mysqli->prepare("
    SELECT v.*, 
           bs.hba1c_value, bs.fpg_value, bs.ppg_value,
           fa.wagner_grade,
           cp.dietary_plan,
           u.full_name AS doctor_name
    FROM visits v
    LEFT JOIN blood_sugar_readings bs ON bs.visit_id = v.visit_id
    LEFT JOIN foot_assessments fa ON fa.visit_id = v.visit_id
    LEFT JOIN care_plan cp ON cp.visit_id = v.visit_id
    LEFT JOIN users u ON v.created_by = u.user_id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
");
$visits_stmt->bind_param('i', $patient_id);
$visits_stmt->execute();
$visits = $visits_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch medical history
$hist_stmt = $mysqli->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY history_id DESC LIMIT 1");
$hist_stmt->bind_param('i', $patient_id);
$hist_stmt->execute();
$history = $hist_stmt->get_result()->fetch_assoc();

// Summary stats
$total_visits = count($visits);
$latest_hba1c = !empty($visits) ? ($visits[0]['hba1c_value'] ?? null) : null;
$latest_wagner = !empty($visits) ? ($visits[0]['wagner_grade'] ?? null) : null;
$latest_risk = $latest_wagner !== null ? ($latest_wagner >= 3 ? 'مرتفع' : ($latest_wagner >= 2 ? 'متوسط' : 'منخفض')) : 'غير محدد';

// Complications summary
$comp_stmt = $mysqli->prepare("SELECT * FROM complications WHERE patient_id = ?");
$comp_stmt->bind_param('i', $patient_id);
$comp_stmt->execute();
$complications_outcome = $comp_stmt->get_result()->fetch_assoc();
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
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #f8f9fa; }
        .report-container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .report-header {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 12px 12px 0 0;
        }
        .report-header h1 { font-size: 1.8rem; margin-bottom: 0.3rem; }
        .report-header p { opacity: 0.9; }
        .patient-id-badge { font-size: 0.9rem; opacity: 0.8; margin-top: 0.5rem; }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: -1.5rem 1rem 1.5rem;
            position: relative;
            z-index: 2;
        }
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .summary-card .stat { font-size: 1.8rem; font-weight: 800; color: var(--primary); }
        .summary-card .label { font-size: 0.85rem; color: #666; margin-top: 0.3rem; }

        .section-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .section-header {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-body { padding: 1.5rem; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.8rem;
        }
        .info-item {}
        .info-item .info-label { font-size: 0.8rem; color: #888; }
        .info-item .info-value { font-size: 1rem; color: #333; font-weight: 600; }

        .visit-timeline {}
        .visit-item {
            padding: 1rem;
            border-right: 3px solid var(--primary);
            margin-right: 1rem;
            margin-bottom: 1rem;
            background: #fafafa;
            border-radius: 0 8px 8px 0;
        }
        .visit-item .visit-date { font-size: 0.85rem; color: var(--primary); font-weight: 600; }
        .visit-item .visit-doctor { font-size: 0.8rem; color: #888; }
        .visit-item .visit-data { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .visit-item .visit-data span { font-size: 0.85rem; color: #555; background: white; padding: 0.2rem 0.6rem; border-radius: 5px; }

        .complication-tag {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
            font-size: 0.8rem;
            margin: 0.2rem;
            background: #ffebee;
            color: #c62828;
        }
        .complication-tag.stable { background: #e8f5e9; color: #2e7d32; }

        .report-footer {
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid #eee;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #aaa;
        }

        .print-controls {
            text-align: center;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .visit-item { break-inside: avoid; }
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
            <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">العودة للمريض</a>
        </div>

        <!-- Report Header -->
        <div class="report-header fade-in">
            <h1><?php echo SITE_NAME_EN; ?></h1>
            <p>تقرير طبي شامل للمريض</p>
            <div class="patient-id-badge">#<?php echo escape_output($patient['file_number']); ?></div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards fade-in">
            <div class="summary-card">
                <div class="stat"><?php echo $total_visits; ?></div>
                <div class="label">عدد الزيارات</div>
            </div>
            <div class="summary-card">
                <div class="stat"><?php echo $latest_hba1c ? number_format($latest_hba1c, 1) . '%' : '—'; ?></div>
                <div class="label">آخر HbA1c</div>
            </div>
            <div class="summary-card">
                <div class="stat"><?php echo escape_output($latest_risk); ?></div>
                <div class="label">مستوى الخطورة</div>
            </div>
            <div class="summary-card">
                <div class="stat"><?php echo $complications_outcome ? count(array_filter($complications_outcome)) : 0; ?></div>
                <div class="label">المضاعفات</div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="section-card fade-in">
            <div class="section-header">👤 معلومات المريض</div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">الاسم الكامل</div>
                        <div class="info-value"><?php echo escape_output($patient['full_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">رقم الملف</div>
                        <div class="info-value"><?php echo escape_output($patient['file_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">تاريخ الميلاد</div>
                        <div class="info-value"><?php echo escape_output($patient['date_of_birth']); ?> (<?php echo $patient['age']; ?> سنة)</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">الجنس</div>
                        <div class="info-value"><?php echo $patient['gender'] === 'ذكر' ? 'ذكر' : 'أنثى'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">الجنسية</div>
                        <div class="info-value"><?php echo escape_output($patient['nationality'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">رقم الهاتف</div>
                        <div class="info-value"><?php echo escape_output($patient['phone_primary']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">المدينة</div>
                        <div class="info-value"><?php echo escape_output($patient['city'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">العنوان</div>
                        <div class="info-value"><?php echo escape_output($patient['address'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">تاريخ أول زيارة</div>
                        <div class="info-value"><?php echo escape_output($patient['first_visit_date'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">المسافة من المركز</div>
                        <div class="info-value"><?php echo escape_output($patient['distance_from_center'] ?? '—'); ?> كم</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical History -->
        <?php if ($history): ?>
        <div class="section-card fade-in">
            <div class="section-header">📋 التاريخ المرضي</div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">نوع السكري</div>
                        <div class="info-value"><?php echo escape_output($history['diabetes_type'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">سنة التشخيص</div>
                        <div class="info-value"><?php echo escape_output($history['diagnosis_year'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">مدة الإصابة</div>
                        <div class="info-value"><?php echo escape_output($history['duration_years'] ?? '—'); ?> سنوات</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">طريقة التشخيص</div>
                        <div class="info-value"><?php echo escape_output($history['diagnosis_method'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">التاريخ العائلي</div>
                        <div class="info-value"><?php echo escape_output($history['family_history'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">التدخين</div>
                        <div class="info-value"><?php echo escape_output($history['smoking_status'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">النشاط البدني</div>
                        <div class="info-value"><?php echo escape_output($history['physical_activity'] ?? '—'); ?></div>
                    </div>
                </div>
                <?php if (!empty($history['family_history'])): ?>
                <div style="margin-top:1rem;">
                    <div class="info-label">التاريخ العائلي</div>
                    <div class="info-value"><?= nl2br(escape_output($history['family_history'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Complications Summary -->
        <?php if ($complications_outcome): $comp_fields = ['has_retinopathy'=>'اعتلال الشبكية','has_nephropathy'=>'اعتلال الكلى','has_neuropathy'=>'اعتلال الأعصاب','has_cad'=>'أمراض القلب','has_cva'=>'الأوعية الدماغية','has_pad'=>'الأوعية الطرفية']; ?>
        <div class="section-card fade-in">
            <div class="section-header">⚠️ ملخص المضاعفات</div>
            <div class="section-body">
                <?php foreach ($comp_fields as $field => $label): ?>
                    <?php if (!empty($complications_outcome[$field])): ?>
                    <span class="complication-tag"><?php echo $label; ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($complications_outcome['diabetic_foot'] && $complications_outcome['diabetic_foot'] !== 'لا يوجد'): ?>
                <span class="complication-tag">قدم السكري: <?php echo $complications_outcome['diabetic_foot']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Visit History -->
        <div class="section-card fade-in">
            <div class="section-header">📅 سجل الزيارات (<?= $total_visits ?> زيارة)</div>
            <div class="section-body">
                <?php if ($visits): ?>
                    <div class="visit-timeline">
                        <?php foreach ($visits as $v): ?>
                        <div class="visit-item">
                            <div class="visit-date">
                                📅 <?= escape_output($v['visit_date'] ?? date('Y-m-d', strtotime($v['created_at']))) ?>
                            </div>
                            <div class="visit-doctor">👨‍⚕️ <?= escape_output($v['doctor_name'] ?? 'غير محدد') ?></div>
                            <div class="visit-data">
                                <?php if ($v['hba1c_value']): ?>
                                    <span>🩸 HbA1c: <?= number_format($v['hba1c_value'], 1) ?>%</span>
                                <?php endif; ?>
                                <?php if ($v['fpg_value']): ?>
                                    <span>🍞 FPG: <?= $v['fpg_value'] ?> mg/dL</span>
                                <?php endif; ?>
                                <?php if ($v['risk_level']): ?>
                                    <span>🦶 الخطورة: <?= escape_output($v['risk_level']) ?></span>
                                <?php endif; ?>
                                <?php if ($v['follow_up_date']): ?>
                                    <span>📆 متابعة: <?= escape_output($v['follow_up_date']) ?></span>
                                <?php endif; ?>
                                <span style="font-size:0.8rem; color:#aaa;">
                                    <a href="<?php echo BASE_URL; ?>/modules/reports/visit_summary.php?visit_id=<?php echo $v['visit_id']; ?>" style="color: var(--primary);">عرض التقرير</a>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; text-align: center;">لا توجد زيارات مسجلة</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="section-card fade-in">
            <div class="section-body" style="text-align:center;">
                <p style="color:#666; font-size:0.9rem;">
                    تم إنشاء هذا التقرير بواسطة نظام <?php echo SITE_NAME; ?> بتاريخ <?= date('Y-m-d h:i A') ?>
                </p>
                <p style="color:#888; font-size:0.8rem; margin-top:0.5rem;">
                    هذا التقرير يحتوي على معلومات طبية سرية — يرجى التعامل معه بسرية تامة
                </p>
            </div>
        </div>

        <!-- Signatures -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px dashed #ccc;">
            <div style="text-align: center;">
                <div style="border-top: 2px solid #333; width: 200px; display: inline-block; margin-bottom: 0.3rem;"></div>
                <p style="font-size:0.85rem; color:#555;">توقيع الطبيب</p>
            </div>
            <div style="text-align: center;">
                <div style="border-top: 2px solid #333; width: 200px; display: inline-block; margin-bottom: 0.3rem;"></div>
                <p style="font-size:0.85rem; color:#555;">ختم العيادة</p>
            </div>
        </div>

        <div class="report-footer">
            <?php echo SITE_NAME; ?> © <?= date('Y') ?> — جميع الحقوق محفوظة
        </div>
    </div>

    <div class="no-print">
            </div>
        </main>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
