<?php
/**
 * Edit User — Task 8
 * Enhanced user editing with all 5 roles
 */
$page_title = 'تعديل مستخدم | Edit User';
require_once __DIR__ . '/../../includes/auth_check.php';
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Location: ' . BASE_URL . '/modules/dashboard.php?error=unauthorized');
    exit;
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = '';

// Fetch user
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php?error=' . urlencode('المستخدم غير موجود'));
    exit;
}

// Check if current user can edit this user
if ($_SESSION['role'] !== 'super_admin' && $user['role'] === 'super_admin') {
    die('❌ لا تملك الصلاحية لتعديل هذا المستخدم');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'رمز CSRF غير صالح';
    }

    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $role = sanitize_input($_POST['role'] ?? '');
    $specialty = sanitize_input($_POST['specialty'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name)) $errors[] = 'الاسم الكامل مطلوب';
    
    $valid_roles = ['super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse'];
    if (!in_array($role, $valid_roles)) $errors[] = 'دور غير صالح';
    
    // Only super_admin can assign super_admin
    if ($role === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
        $errors[] = 'فقط Super Admin يمكنه تعيين هذا الدور';
    }

    // Email uniqueness
    if ($email) {
        $check = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param('si', $email, $user_id);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) $errors[] = 'البريد الإلكتروني مستخدم من قبل شخص آخر';
    }

    if (empty($errors)) {
        $sql = "UPDATE users SET full_name=?, email=?, phone=?, role=?, specialty=?, is_active=? WHERE user_id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sssssii', $full_name, $email, $phone, $role, $specialty, $is_active, $user_id);
        
        if ($stmt->execute()) {
            // Change password if provided
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = 'كلمة المرور غير متطابقة';
                } else {
                    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $pstmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $pstmt->bind_param('si', $hashed, $user_id);
                    $pstmt->execute();
                }
            }
            
            if (empty($errors)) {
                $success = '✅ تم حفظ التغييرات بنجاح';
                // Refresh user data
                $stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            }
        } else {
            $errors[] = 'حدث خطأ: ' . $mysqli->error;
        }
    }
}
?>
<style>
.form-section { margin-bottom: 24px; }
.form-section-title { 
    font-family: 'Cairo', sans-serif; font-size: 15px; font-weight: 800; 
    color: var(--white); background: linear-gradient(90deg, var(--teal), var(--teal-light));
    padding: 8px 18px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; 
}
</style>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">✏️ تعديل المستخدم</h1>
        <p class="page-subtitle"><?php echo escape_output($user['username']); ?> — <?php echo escape_output($user['full_name']); ?></p>
    </div>
    <a href="index.php" class="btn btn-secondary">🔙 العودة</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul style="margin:0;padding-right:1.5rem;">
        <?php foreach ($errors as $e): ?><li><?php echo escape_output($e); ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="card">
    <form method="post">
        <?php echo csrf_field(); ?>
        
        <div class="form-section">
            <div class="form-section-title">👤 المعلومات الأساسية</div>
            <div class="form-grid">
                <div class="field">
                    <label>اسم المستخدم</label>
                    <input type="text" value="<?php echo escape_output($user['username']); ?>" disabled style="background:#f0f0f0;">
                    <small style="color:#94a3b8;">لا يمكن تغيير اسم المستخدم</small>
                </div>
                <div class="field">
                    <label>الاسم الكامل <span class="required">*</span></label>
                    <input type="text" name="full_name" value="<?php echo escape_output($user['full_name']); ?>" required>
                </div>
                <div class="field">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" value="<?php echo escape_output($user['email'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>رقم الهاتف</label>
                    <input type="text" name="phone" value="<?php echo escape_output($user['phone'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-section-title">🔑 الصلاحيات</div>
            <div class="form-grid">
                <div class="field">
                    <label>الدور <span class="required">*</span></label>
                    <select name="role" required>
                        <?php 
                        $roles = [
                            'super_admin' => 'Super Admin',
                            'admin' => 'مدير (Admin)',
                            'doctor' => 'طبيب (Doctor)',
                            'medical_assistant' => 'مساعد طبي (Medical Assistant)',
                            'nurse' => 'ممرض (Nurse)'
                        ];
                        $can_assign_super = $_SESSION['role'] === 'super_admin';
                        foreach ($roles as $val => $label):
                            if ($val === 'super_admin' && !$can_assign_super) continue;
                        ?>
                        <option value="<?php echo $val; ?>" <?php echo $user['role'] === $val ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>التخصص (للأطباء)</label>
                    <input type="text" name="specialty" value="<?php echo escape_output($user['specialty'] ?? ''); ?>" placeholder="مثال: غدد صماء">
                </div>
                <div class="field">
                    <label>الحالة</label>
                    <label class="check-item" style="margin-top:8px;">
                        <input type="checkbox" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                        <span>الحساب نشط</span>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-section-title">🔒 تغيير كلمة المرور (اختياري)</div>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">اترك الحقول فارغة إذا كنت لا تريد تغيير كلمة المرور</p>
            <div class="form-grid">
                <div class="field">
                    <label>كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" placeholder="6 أحرف على الأقل" minlength="6">
                </div>
                <div class="field">
                    <label>تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" placeholder="أعد إدخال كلمة المرور">
                </div>
            </div>
        </div>
        
        <div class="flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">💾 حفظ التغييرات</button>
            <a href="index.php" class="btn btn-secondary">❌ إلغاء</a>
        </div>
    </form>
</div>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
