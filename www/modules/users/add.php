<?php
/**
 * User Management - Add new user (UI Upgrade v2.0)
 * Includes all 5 roles: super_admin, admin, doctor, medical_assistant, nurse
 */
$page_title = '➕ إضافة مستخدم جديد | Add User';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (!has_role(['super_admin', 'admin'])) {
    header('Location: ' . BASE_URL . '/modules/dashboard.php?error=unauthorized');
    exit;
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'رمز CSRF غير صالح';
    }

    $username = sanitize_input($_POST['username'] ?? '');
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    $specialty = sanitize_input($_POST['specialty'] ?? '');

    $valid_roles = ['super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse'];

    // Validation
    if (empty($username) || strlen($username) < 3) $errors[] = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
    if (empty($full_name)) $errors[] = 'الاسم الكامل مطلوب';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح';
    if (strlen($password) < 6) $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    if ($password !== $confirm_password) $errors[] = 'كلمة المرور غير متطابقة';
    if (!in_array($role, $valid_roles)) $errors[] = 'دور غير صالح';
    
    // Only super_admin can create super_admin
    if ($role === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
        $errors[] = 'فقط Super Admin يمكنه إنشاء مستخدمين بهذا الدور';
    }

    // Check unique username
    if (empty($errors)) {
        $check = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param('s', $username);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) $errors[] = 'اسم المستخدم موجود مسبقاً';
    }

    // Check unique email
    if (empty($errors) && $email) {
        $check = $mysqli->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) $errors[] = 'البريد الإلكتروني موجود مسبقاً';
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, role, specialty) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', $username, $hashed, $full_name, $email, $phone, $role, $specialty);

        if ($stmt->execute()) {
            header('Location: ' . BASE_URL . '/modules/users/index.php?success=' . urlencode('✅ تم إضافة المستخدم بنجاح'));
            exit;
        } else {
            $errors[] = 'حدث خطأ في إضافة المستخدم: ' . $mysqli->error;
        }
    }
}

// Role options for the form
$role_options = [
    'super_admin' => ['label' => 'Super Admin', 'color' => 'danger', 'icon' => '👑', 'desc' => 'صلاحية كاملة على جميع أجزاء النظام'],
    'admin' => ['label' => 'مدير (Admin)', 'color' => 'danger', 'icon' => '⚙️', 'desc' => 'إدارة المستخدمين والإعدادات'],
    'doctor' => ['label' => 'طبيب (Doctor)', 'color' => 'primary', 'icon' => '🩺', 'desc' => 'المرضى والزيارات والتقييمات'],
    'medical_assistant' => ['label' => 'مساعد طبي (Medical Assistant)', 'color' => 'info', 'icon' => '🩹', 'desc' => 'إدخال البيانات وإدارة الفحوصات'],
    'nurse' => ['label' => 'ممرض (Nurse)', 'color' => 'success', 'icon' => '💉', 'desc' => 'عرض بيانات المرضى فقط'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_output($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/animations.css">
    <style>
        .role-card {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .role-card:hover {
            border-color: var(--teal-light);
            background: var(--teal-pale);
        }
        .role-card.selected {
            border-color: var(--teal);
            background: var(--teal-pale);
            box-shadow: 0 0 0 3px rgba(10, 126, 110, 0.15);
        }
        .role-card .role-icon {
            font-size: 28px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            flex-shrink: 0;
        }
        .role-card .role-info h4 {
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            font-weight: 800;
            color: var(--text-heading);
        }
        .role-card .role-info p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .role-card input[type="radio"] {
            display: none;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
            <div class="page-content page-entrance">
                <div class="page-header flex justify-between items-center flex-wrap gap-3">
                    <div>
                        <h1 class="page-title">➕ <?= $page_title ?></h1>
                        <p class="page-subtitle">إنشاء حساب مستخدم جديد مع صلاحيات كاملة</p>
                    </div>
                    <a href="index.php" class="btn btn-secondary">🔙 العودة إلى القائمة</a>
                </div>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul style="margin:0; padding-right:1.5rem;">
                            <?php foreach ($errors as $e): ?>
                                <li><?= escape_output($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card fade-in">
                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>

                        <!-- Account Information -->
                        <div class="form-section">
                            <div class="form-section-title">👤 المعلومات الأساسية</div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="username">اسم المستخدم <span class="required">*</span></label>
                                    <input type="text" id="username" name="username" 
                                           value="<?= escape_output($_POST['username'] ?? '') ?>" required minlength="3"
                                           placeholder="اسم المستخدم للنظام">
                                </div>
                                <div class="field">
                                    <label for="full_name">الاسم الكامل <span class="required">*</span></label>
                                    <input type="text" id="full_name" name="full_name" 
                                           value="<?= escape_output($_POST['full_name'] ?? '') ?>" required
                                           placeholder="الاسم الثلاثي">
                                </div>
                                <div class="field">
                                    <label for="email">البريد الإلكتروني</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?= escape_output($_POST['email'] ?? '') ?>"
                                           placeholder="user@example.com">
                                </div>
                                <div class="field">
                                    <label for="phone">رقم الهاتف</label>
                                    <input type="text" id="phone" name="phone" 
                                           value="<?= escape_output($_POST['phone'] ?? '') ?>"
                                           placeholder="05XXXXXXXX">
                                </div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="form-section">
                            <div class="form-section-title">🔒 كلمة المرور</div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="password">كلمة المرور <span class="required">*</span></label>
                                    <input type="password" id="password" name="password" 
                                           required minlength="6" placeholder="6 أحرف على الأقل">
                                </div>
                                <div class="field">
                                    <label for="confirm_password">تأكيد كلمة المرور <span class="required">*</span></label>
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           required placeholder="أعد إدخال كلمة المرور">
                                </div>
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div class="form-section">
                            <div class="form-section-title">🔑 الصلاحيات — اختر الدور</div>
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                                <?php $can_assign_super = $_SESSION['role'] === 'super_admin'; ?>
                                <?php foreach ($role_options as $val => $opt): 
                                    if ($val === 'super_admin' && !$can_assign_super) continue;
                                    $selected = ($_POST['role'] ?? '') === $val || (!$can_assign_super && $val === 'admin') ? 'selected' : '';
                                ?>
                                <label class="role-card <?php echo $selected; ?>" onclick="this.querySelector('input[type=radio]').checked=true;document.querySelectorAll('.role-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected');">
                                    <input type="radio" name="role" value="<?php echo $val; ?>" <?php echo $selected ? 'checked' : ''; ?>>
                                    <div class="role-icon" style="background:var(--<?php echo $opt['color']; ?>-pale, var(--teal-pale));">
                                        <?php echo $opt['icon']; ?>
                                    </div>
                                    <div class="role-info">
                                        <h4><?php echo $opt['label']; ?></h4>
                                        <p><?php echo $opt['desc']; ?></p>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Specialty (for doctors) -->
                        <div class="form-section" id="specialty-section" style="<?php echo ($_POST['role'] ?? '') === 'doctor' ? '' : 'display:none;'; ?>">
                            <div class="form-section-title">🏥 التخصص (للأطباء)</div>
                            <div class="form-grid">
                                <div class="field">
                                    <label>التخصص</label>
                                    <input type="text" name="specialty" 
                                           value="<?= escape_output($_POST['specialty'] ?? '') ?>"
                                           placeholder="مثال: غدد صماء وسكري">
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-4" style="padding-top:16px;border-top:1px solid var(--border);">
                            <button type="submit" class="btn btn-primary" style="padding:10px 28px;font-size:15px;">💾 حفظ المستخدم</button>
                            <a href="index.php" class="btn btn-secondary" style="padding:10px 28px;font-size:15px;">❌ إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
        </div>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    <script>
    // Show/hide specialty field when role changes
    document.querySelectorAll('input[name="role"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const specialtySection = document.getElementById('specialty-section');
            if (this.value === 'doctor') {
                specialtySection.style.display = 'block';
            } else {
                specialtySection.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>
