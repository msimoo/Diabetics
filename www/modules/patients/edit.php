<?php
/**
 * Edit Patient Page
 */
$page_title = 'تعديل بيانات المريض | Edit Patient';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch patient data
$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ المريض غير موجود</div></div></div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '❌ طلب غير مصرح به';
    } else {
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $age = $_POST['age'] ?? null;
        $gender = sanitize_input($_POST['gender'] ?? '');
        $phone_primary = sanitize_input($_POST['phone_primary'] ?? '');
        $phone_secondary = sanitize_input($_POST['phone_secondary'] ?? '');
        $identity_number = sanitize_input($_POST['identity_number'] ?? '');
        $nationality = sanitize_input($_POST['nationality'] ?? '');
        $marital_status = sanitize_input($_POST['marital_status'] ?? '');
        $occupation = sanitize_input($_POST['occupation'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $distance = $_POST['distance_from_center'] ? (float)$_POST['distance_from_center'] : null;

        if (empty($full_name) || empty($gender)) {
            $error = '❌ الاسم الكامل والجنس حقول مطلوبة';
        } else {
            if ($date_of_birth && empty($age)) {
                $age = calculate_age($date_of_birth);
            }

            $stmt = $mysqli->prepare("UPDATE patients SET 
                full_name=?, date_of_birth=?, age=?, gender=?, phone_primary=?, phone_secondary=?,
                identity_number=?, nationality=?, marital_status=?, occupation=?, address=?, city=?,
                distance_from_center=?
                WHERE patient_id=?");
            
            $stmt->bind_param('ssisssssssssdi', 
                $full_name, $date_of_birth, $age, $gender,
                $phone_primary, $phone_secondary, $identity_number, $nationality,
                $marital_status, $occupation, $address, $city,
                $distance, $patient_id
            );

            if ($stmt->execute()) {
                $success = '✅ تم تحديث البيانات بنجاح';
                // Refresh patient data
                $stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
                $stmt->bind_param('i', $patient_id);
                $stmt->execute();
                $patient = $stmt->get_result()->fetch_assoc();
            } else {
                $error = '❌ حدث خطأ: ' . $stmt->error;
            }
        }
    }
}
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            <div class="page-header">
                <h1 class="page-title">✏️ تعديل بيانات المريض</h1>
                <p class="page-subtitle"><?php echo escape_output($patient['full_name']); ?> - <?php echo escape_output($patient['file_number']); ?></p>
            </div>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

            <div class="card">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <div class="form-section">
                        <div class="form-section-title">👤 البيانات الشخصية</div>
                        <div class="form-grid form-grid-3">
                            <div class="field col-span-2">
                                <label>الاسم الكامل <span class="required">*</span></label>
                                <input type="text" name="full_name" value="<?php echo escape_output($patient['full_name']); ?>" required>
                            </div>
                            <div class="field">
                                <label>رقم الملف</label>
                                <input type="text" value="<?php echo escape_output($patient['file_number']); ?>" disabled style="background:#f0f0f0;">
                            </div>
                            <div class="field">
                                <label>تاريخ الميلاد</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo $patient['date_of_birth']; ?>">
                            </div>
                            <div class="field">
                                <label>العمر</label>
                                <input type="number" name="age" id="age" value="<?php echo $patient['age']; ?>">
                            </div>
                            <div class="field">
                                <label>الجنس <span class="required">*</span></label>
                                <select name="gender" required>
                                    <option value="ذكر" <?php echo $patient['gender'] === 'ذكر' ? 'selected' : ''; ?>>ذكر</option>
                                    <option value="أنثى" <?php echo $patient['gender'] === 'أنثى' ? 'selected' : ''; ?>>أنثى</option>
                                </select>
                            </div>
                            <div class="field"><label>رقم الهاتف</label><input type="tel" name="phone_primary" value="<?php echo escape_output($patient['phone_primary'] ?? ''); ?>"></div>
                            <div class="field"><label>رقم آخر</label><input type="tel" name="phone_secondary" value="<?php echo escape_output($patient['phone_secondary'] ?? ''); ?>"></div>
                            <div class="field"><label>رقم الهوية</label><input type="text" name="identity_number" value="<?php echo escape_output($patient['identity_number'] ?? ''); ?>"></div>
                            <div class="field"><label>الجنسية</label><input type="text" name="nationality" value="<?php echo escape_output($patient['nationality'] ?? ''); ?>"></div>
                            <div class="field">
                                <label>الحالة الاجتماعية</label>
                                <select name="marital_status">
                                    <option value="">-- اختر --</option>
                                    <?php foreach (['أعزب/عزباء', 'متزوج/ة', 'مطلق/ة', 'أرمل/ة'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $patient['marital_status'] === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label>المهنة</label><input type="text" name="occupation" value="<?php echo escape_output($patient['occupation'] ?? ''); ?>"></div>
                            <div class="field col-span-2"><label>العنوان</label><input type="text" name="address" value="<?php echo escape_output($patient['address'] ?? ''); ?>"></div>
                            <div class="field"><label>المدينة</label><input type="text" name="city" value="<?php echo escape_output($patient['city'] ?? ''); ?>"></div>
                            <div class="field"><label>المسافة (كم)</label><input type="number" name="distance_from_center" step="0.1" value="<?php echo $patient['distance_from_center']; ?>"></div>
                        </div>
                    </div>
                    <div class="flex justify-between mt-5">
                        <a href="view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
                        <button type="submit" name="update_patient" class="btn btn-primary">💾 حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
