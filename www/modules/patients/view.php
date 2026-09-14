<?php
/**
 * View Patient Profile
 */
$page_title = 'ملف المريض | Patient Profile';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch patient
$stmt = $mysqli->prepare("SELECT p.*, u.full_name as created_by_name 
                          FROM patients p 
                          LEFT JOIN users u ON p.created_by = u.user_id 
                          WHERE p.patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ المريض غير موجود</div></div></div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch medical history
$stmt = $mysqli->prepare("SELECT * FROM medical_history WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_assoc();

// Fetch complications
$stmt = $mysqli->prepare("SELECT * FROM complications WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$complications = $stmt->get_result()->fetch_assoc();

// Fetch recent visits
$stmt = $mysqli->prepare("SELECT v.*, u.full_name as doctor_name 
                          FROM visits v 
                          LEFT JOIN users u ON v.created_by = u.user_id 
                          WHERE v.patient_id = ? 
                          ORDER BY v.visit_date DESC LIMIT 10");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$visits = $stmt->get_result();
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <!-- Patient Header -->
            <div class="card mb-5" style="background:linear-gradient(135deg, var(--dark) 0%, var(--teal) 100%);color:#fff;">
                <div class="flex flex-wrap gap-4 items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div style="font-size:48px;">👤</div>
                        <div>
                            <h1 style="font-family:'Cairo',sans-serif;font-size:24px;font-weight:900;">
                                <?php echo escape_output($patient['full_name']); ?>
                            </h1>
                            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:6px;color:rgba(255,255,255,0.75);font-size:13px;">
                                <span>📁 <?php echo escape_output($patient['file_number']); ?></span>
                                <?php if ($patient['age']): ?><span>👤 <?php echo $patient['age']; ?> سنة</span><?php endif; ?>
                                <span>⚤ <?php echo $patient['gender'] ?? '—'; ?></span>
                                <?php if ($patient['phone_primary']): ?><span>📞 <?php echo escape_output($patient['phone_primary']); ?></span><?php endif; ?>
                                <?php if ($patient['identity_number']): ?><span>🆔 <?php echo escape_output($patient['identity_number']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <a href="<?php echo BASE_URL; ?>/modules/visits/add.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary" style="background:var(--gold);color:var(--dark);">
                            🩺 زيارة جديدة
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/lab_tests/order.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                            🧪 فحوصات
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/auto_instructions/patient_view.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                            📋 تعليمات
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/patients/edit.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                            ✏️ تعديل
                        </a>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-4">
                <!-- Personal Info Card -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">📋 البيانات الشخصية</div></div>
                    <table style="font-size:13px;">
                        <tr><td style="font-weight:700;width:40%;padding:6px 0;">الحالة الاجتماعية</td><td style="padding:6px 0;"><?php echo $patient['marital_status'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">المهنة</td><td style="padding:6px 0;"><?php echo escape_output($patient['occupation'] ?? '—'); ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">الجنسية</td><td style="padding:6px 0;"><?php echo $patient['nationality'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">المدينة</td><td style="padding:6px 0;"><?php echo escape_output($patient['city'] ?? '—'); ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">العنوان</td><td style="padding:6px 0;"><?php echo escape_output($patient['address'] ?? '—'); ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">المسافة</td><td style="padding:6px 0;"><?php echo $patient['distance_from_center'] ? $patient['distance_from_center'] . ' كم' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">أول زيارة</td><td style="padding:6px 0;"><?php echo $patient['first_visit_date'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">تاريخ التسجيل</td><td style="padding:6px 0;"><?php echo date('Y-m-d', strtotime($patient['created_at'])); ?></td></tr>
                    </table>
                </div>

                <!-- Medical History Card -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">🩺 التاريخ الطبي</div></div>
                    <?php if ($history): ?>
                    <table style="font-size:13px;">
                        <tr><td style="font-weight:700;width:40%;padding:6px 0;">نوع السكري</td><td style="padding:6px 0;"><?php echo $history['diabetes_type'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">سنة التشخيص</td><td style="padding:6px 0;"><?php echo $history['diagnosis_year'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">مدة الإصابة</td><td style="padding:6px 0;"><?php echo $history['duration_years'] ? $history['duration_years'] . ' سنوات' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">طريقة التشخيص</td><td style="padding:6px 0;"><?php echo $history['diagnosis_method'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">التاريخ العائلي</td><td style="padding:6px 0;"><?php echo $history['family_history'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">التدخين</td><td style="padding:6px 0;"><?php echo $history['smoking_status'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:6px 0;">النشاط البدني</td><td style="padding:6px 0;"><?php echo $history['physical_activity'] ?: '—'; ?></td></tr>
                    </table>
                    <?php else: ?>
                    <p style="color:#94a3b8;">لا يوجد تاريخ طبي مسجل</p>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/modules/visits/medical_history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary mt-3">➕ إضافة تاريخ طبي</a>
                </div>

                <!-- Complications Card -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">⚕️ المضاعفات</div></div>
                    <?php if ($complications): ?>
                    <table style="font-size:13px;">
                        <tr><td style="font-weight:700;width:50%;padding:4px 0;">اعتلال الشبكية</td><td style="padding:4px 0;"><?php echo $complications['has_retinopathy'] ? '✅' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:4px 0;">اعتلال الكلى</td><td style="padding:4px 0;"><?php echo $complications['has_nephropathy'] ? '✅' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:4px 0;">اعتلال الأعصاب</td><td style="padding:4px 0;"><?php echo $complications['has_neuropathy'] ? '✅' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:4px 0;">أمراض القلب</td><td style="padding:4px 0;"><?php echo $complications['has_cad'] ? '✅' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:4px 0;">الأوعية الدماغية</td><td style="padding:4px 0;"><?php echo $complications['has_cva'] ? '✅' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:4px 0;">الأوعية الطرفية</td><td style="padding:4px 0;"><?php echo $complications['has_pad'] ? '✅' : '—'; ?></td></tr>
                        <tr><td style="font-weight:700;padding:4px 0;">قدم السكري</td><td style="padding:4px 0;"><?php echo $complications['diabetic_foot'] ?: '—'; ?></td></tr>
                    </table>
                    <?php else: ?>
                    <p style="color:#94a3b8;">لم تسجل مضاعفات بعد</p>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/modules/visits/complications.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary mt-3">➕ تسجيل مضاعفات</a>
                </div>
            </div>

            <!-- Visit History -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="card-title">🕐 سجل الزيارات</div>
                    <a href="<?php echo BASE_URL; ?>/modules/visits/add.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">➕ زيارة جديدة</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>رقم الزيارة</th>
                                <th>التاريخ</th>
                                <th>السبب</th>
                                <th>الطبيب</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($visits->num_rows === 0): ?>
                            <tr><td colspan="5" style="color:#94a3b8;">لا توجد زيارات</td></tr>
                            <?php else: ?>
                                <?php while ($v = $visits->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $v['visit_number']; ?></strong></td>
                                    <td><?php echo $v['visit_date']; ?></td>
                                    <td><?php echo $v['visit_reason'] ?: '—'; ?></td>
                                    <td><?php echo $v['doctor_name'] ?: '—'; ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $v['visit_id']; ?>" class="btn btn-sm btn-primary">عرض</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
