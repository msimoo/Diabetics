<?php
/**
 * Delete (Deactivate) Patient
 * Soft-deletes a patient by setting is_active = 0
 */
$page_title = 'حذف المريض | Delete Patient';
require_once __DIR__ . '/../../includes/auth_check.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch patient
$stmt = $mysqli->prepare("SELECT patient_id, full_name, file_number FROM patients WHERE patient_id = ? AND is_active = 1");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    $_SESSION['error'] = '❌ المريض غير موجود';
    header('Location: index.php');
    exit;
}

// CSRF verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = '❌ طلب غير مصرح به';
    } else {
        $stmt = $mysqli->prepare("UPDATE patients SET is_active = 0 WHERE patient_id = ?");
        $stmt->bind_param('i', $patient_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "✅ تم حذف المريض {$patient['full_name']} بنجاح";
        } else {
            $_SESSION['error'] = '❌ حدث خطأ أثناء الحذف';
        }
    }
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            <div class="card" style="max-width:500px;margin:40px auto;text-align:center;">
                <div style="font-size:64px;margin-bottom:16px;">⚠️</div>
                <h2 style="font-family:'Cairo',sans-serif;font-size:20px;margin-bottom:8px;">تأكيد حذف المريض</h2>
                <p style="color:var(--gray);margin-bottom:20px;">
                    هل أنت متأكد من حذف المريض؟<br>
                    <strong><?php echo escape_output($patient['full_name']); ?></strong><br>
                    📁 <?php echo escape_output($patient['file_number']); ?>
                </p>
                <p style="color:var(--red);font-size:13px;margin-bottom:20px;">
                    ⚠️ هذا الإجراء سيعطل ملف المريض ولن يظهر في قائمة المرضى.<br>
                    يمكنك استعادته لاحقاً عن طريق مسؤول النظام.
                </p>
                <div class="flex gap-3" style="justify-content:center;">
                    <a href="index.php" class="btn btn-secondary">🔙 إلغاء</a>
                    <form method="post" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="confirm_delete" class="btn btn-danger">🗑️ تأكيد الحذف</button>
                    </form>
                </div>
            </div>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
