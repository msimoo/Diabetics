<?php
/**
 * User Management - List all users
 */
$page_title = 'إدارة المستخدمين | User Management';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Only admin/super_admin can manage users
if (!has_role(['super_admin', 'admin'])) {
    header('Location: ' . BASE_URL . '/modules/dashboard.php?error=unauthorized');
    exit;
}
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch all users
$users_stmt = $mysqli->prepare("SELECT user_id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
$users_stmt->execute();
$users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

            <div class="page-content page-entrance">
                <div class="page-header">
                    <h1><i class="icon">👥</i> <?= $page_title ?></h1>
                    <div class="header-actions">
                        <a href="add.php" class="btn btn-primary">+ إضافة مستخدم</a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= escape_output($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= escape_output($error) ?></div>
                <?php endif; ?>

                <div class="card fade-in">
                    <div class="card-body" style="padding:0;">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>اسم المستخدم</th>
                                        <th>الاسم الكامل</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الدور</th>
                                        <th>الحالة</th>
                                        <th>آخر دخول</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $i => $user): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= escape_output($user['username']) ?></td>
                                        <td><?= escape_output($user['full_name']) ?></td>
                                        <td><?= escape_output($user['email'] ?? '—') ?></td>
                                        <td>
                                            <?php
                                            $role_colors = ['super_admin' => 'danger', 'admin' => 'danger', 'doctor' => 'primary', 'medical_assistant' => 'info', 'nurse' => 'success'];
                                            $role_labels = ['super_admin' => 'مدير عام', 'admin' => 'مدير', 'doctor' => 'طبيب', 'medical_assistant' => 'مساعد طبي', 'nurse' => 'ممرض'];
                                            $rc = $role_colors[$user['role']] ?? 'secondary';
                                            $rl = $role_labels[$user['role']] ?? $user['role'];
                                            ?>
                                            <span class="badge badge-<?= $rc ?>"><?= $rl ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $user['is_active'] ? 'نشط' : 'غير نشط' ?>
                                            </span>
                                        </td>
                                        <td><?= $user['last_login'] ? date('Y-m-d h:i A', strtotime($user['last_login'])) : '—' ?></td>
                                        <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <a href="edit.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-primary" title="تعديل">✏️</a>
                                            <a href="profile.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-secondary" title="الملف الشخصي">👤</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$users): ?>
                                    <tr><td colspan="9" style="text-align:center; color:#888;">لا يوجد مستخدمون</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
        </main>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
