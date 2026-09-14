<?php
/**
 * Add Patient Page
 */
$page_title = 'إضافة مريض | Add Patient';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers_lookup.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_patient'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '❌ طلب غير مصرح به';
    } else {
        // Gather form data
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
        $first_visit = $_POST['first_visit_date'] ?? date('Y-m-d');

        // Validate required fields
        if (empty($full_name) || empty($gender)) {
            $error = '❌ الاسم الكامل والجنس حقول مطلوبة';
        } else {
            // Auto-calculate age
            if ($date_of_birth && empty($age)) {
                $age = calculate_age($date_of_birth);
            }

            // Generate file number
            $file_number = generate_file_number();

            $stmt = $mysqli->prepare("INSERT INTO patients 
                (file_number, full_name, date_of_birth, age, gender, phone_primary, phone_secondary, 
                 identity_number, nationality, marital_status, occupation, address, city, 
                 distance_from_center, first_visit_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $created_by = $_SESSION['user_id'];
            $stmt->bind_param('sssisssssssssdsi', 
                $file_number, $full_name, $date_of_birth, $age, $gender,
                $phone_primary, $phone_secondary, $identity_number, $nationality,
                $marital_status, $occupation, $address, $city,
                $distance, $first_visit, $created_by
            );

            if ($stmt->execute()) {
                $patient_id = $stmt->insert_id;
                $success = "✅ تم إضافة المريض بنجاح - رقم الملف: $file_number";
                echo "<script>window.location.href='view.php?id=$patient_id';</script>";
            } else {
                $error = '❌ حدث خطأ: ' . $stmt->error;
            }
        }
    }
}

$file_number = generate_file_number();
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            <div class="page-header">
                <h1 class="page-title">➕ إضافة مريض جديد</h1>
                <p class="page-subtitle">New Patient Registration</p>
            </div>

            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

            <div class="card">
                <form method="post" id="patientForm">
                    <?php echo csrf_field(); ?>
                    
                    <!-- Patient Info Section -->
                    <div class="form-section">
                        <div class="form-section-title">👤 البيانات الشخصية</div>
                        <div class="form-grid form-grid-3">
                            <div class="field col-span-2">
                                <label>الاسم الكامل <span class="required">*</span></label>
                                <input type="text" name="full_name" placeholder="الاسم الرباعي" required>
                            </div>
                            <div class="field">
                                <label>رقم الملف</label>
                                <input type="text" value="<?php echo $file_number; ?>" disabled style="background:#f0f0f0;">
                                <small style="color:#94a3b8;">يُولد تلقائياً</small>
                            </div>

                            <div class="field">
                                <label>تاريخ الميلاد</label>
                                <input type="date" name="date_of_birth" id="date_of_birth">
                            </div>
                            <div class="field">
                                <label>العمر</label>
                                <input type="number" name="age" id="age" placeholder="يُحسب تلقائياً">
                            </div>
                            <div class="field">
                                <label>الجنس <span class="required">*</span></label>
                                <select name="gender" required>
                                    <option value="">-- اختر --</option>
                                    <option value="ذكر">ذكر</option>
                                    <option value="أنثى">أنثى</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>رقم الهاتف</label>
                                <input type="tel" name="phone_primary" placeholder="05XXXXXXXX">
                            </div>
                            <div class="field">
                                <label>رقم هاتف آخر</label>
                                <input type="tel" name="phone_secondary" placeholder="05XXXXXXXX">
                            </div>
                            <div class="field">
                                <label>رقم الهوية / الإقامة</label>
                                <input type="text" name="identity_number" placeholder="10 أرقام">
                            </div>

                            <div class="field">
                                <label>الجنسية</label>
                                <input type="text" name="nationality">
                            </div>
                            <div class="field">
                                <label>الحالة الاجتماعية</label>
                                <select name="marital_status">
                                    <option value="">-- اختر --</option>
                                    <option value="أعزب/عزباء">أعزب/عزباء</option>
                                    <option value="متزوج/ة">متزوج/ة</option>
                                    <option value="مطلق/ة">مطلق/ة</option>
                                    <option value="أرمل/ة">أرمل/ة</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>المهنة</label>
                                <input type="text" name="occupation">
                            </div>

                            <div class="field col-span-2">
                                <label>العنوان</label>
                                <input type="text" name="address" placeholder="المدينة، الحي، الشارع">
                            </div>
                            <div class="field">
                                <label>المدينة</label>
                                <select name="city">
                                    <?php echo get_cities_options($mysqli); ?>
                                </select>
                            </div>

                            <div class="field">
                                <label>المسافة من المركز (كم)</label>
                                <input type="number" name="distance_from_center" step="0.1" min="0">
                            </div>
                            <div class="field">
                                <label>تاريخ أول زيارة</label>
                                <input type="date" name="first_visit_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between mt-5">
                        <a href="index.php" class="btn btn-secondary">🔙 رجوع</a>
                        <button type="submit" name="save_patient" class="btn btn-primary">💾 حفظ بيانات المريض</button>
                    </div>
                </form>
            </div>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
