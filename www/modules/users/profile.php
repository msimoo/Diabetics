<?php
/**
 * User Profile Module
 * View and edit current user's profile
 */
$page_title = 'الملف الشخصي | Profile';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$page_title = 'الملف الشخصي';
$user_id = (int)($_SESSION['user_id']);
$success = '';
$errors = [];

// Fetch current user data
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'رمز CSRF غير صالح';
    }

    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (empty($full_name)) $errors[] = 'الاسم الكامل مطلوب';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح';

    // Check email uniqueness
    if (empty($errors) && $email) {
        $check = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->bind_param('si', $email, $user_id);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) $errors[] = 'البريد الإلكتروني مستخدم من قبل شخص آخر';
    }

    if (empty($errors)) {
        $update = $mysqli->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $update->bind_param('ssi', $full_name, $email, $user_id);
        if ($update->execute()) {
            $_SESSION['full_name'] = $full_name;
            $success = 'تم تحديث الملف الشخصي بنجاح';
            $user['full_name'] = $full_name;
            $user['email'] = $email;
        } else {
            $errors[] = 'حدث خطأ في تحديث الملف الشخصي';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'رمز CSRF غير صالح';
    }

    $current = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password_hash'])) $errors[] = 'كلمة المرور الحالية غير صحيحة';
    if (strlen($new_password) < 6) $errors[] = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل';
    if ($new_password !== $confirm) $errors[] = 'كلمة المرور غير متطابقة';

    if (empty($errors)) {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $update = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $update->bind_param('si', $hashed, $user_id);
        if ($update->execute()) {
            $success = 'تم تغيير كلمة المرور بنجاح';
        } else {
            $errors[] = 'حدث خطأ في تغيير كلمة المرور';
        }
    }
}
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
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
            border: 3px solid rgba(255,255,255,0.4);
        }
        .profile-header h2 { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .profile-header .role-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #888; font-size: 0.9rem; }
        .info-row .value { color: #333; font-weight: 600; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
        <div class="page-content page-entrance">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= escape_output($success) ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul style="margin:0; padding-right:1.5rem;">
                            <?php foreach ($errors as $e): ?>
                                <li><?= escape_output($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="profile-header fade-in">
                    <div class="profile-avatar">👤</div>
                    <h2><?= escape_output($user['full_name']) ?></h2>
                    <span class="role-badge"><?= $user['role'] === 'admin' ? 'مدير النظام' : 'مستخدم' ?></span>
                    <p style="margin-top: 0.5rem; opacity: 0.8; font-size: 0.9rem;">
                        آخر دخول: <?= $user['last_login'] ? date('Y-m-d h:i A', strtotime($user['last_login'])) : 'أول مرة' ?>
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <!-- Profile Info -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3>📋 معلومات الحساب</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="label">اسم المستخدم</span>
                                <span class="value"><?= escape_output($user['username']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">الدور</span>
                                <span class="value"><?= $user['role'] === 'admin' ? 'مدير' : 'مستخدم' ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">الحالة</span>
                                <span class="value" style="color:<?= $user['is_active'] ? '#2e7d32' : '#c62828' ?>">
                                    <?= $user['is_active'] ? 'نشط' : 'غير نشط' ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="label">تاريخ التسجيل</span>
                                <span class="value"><?= date('Y-m-d', strtotime($user['created_at'])) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">آخر تحديث</span>
                                <span class="value"><?= date('Y-m-d h:i A', strtotime($user['updated_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3>✏️ تعديل الملف الشخصي</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?= csrf_field() ?>
                                <div class="form-group">
                                    <label for="full_name">الاسم الكامل</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" 
                                           value="<?= escape_output($user['full_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">البريد الإلكتروني</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?= escape_output($user['email'] ?? '') ?>" required>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        💾 حفظ التغييرات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card fade-in" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>🔑 تغيير كلمة المرور</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?= csrf_field() ?>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="current_password">كلمة المرور الحالية</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password">كلمة المرور الجديدة</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">تأكيد كلمة المرور</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        🔒 تغيير كلمة المرور
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
        </div>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/forms.js"></script>
</body>
</html>
