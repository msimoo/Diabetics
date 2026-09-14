<?php
/**
 * Diabetes Types Management — Dedicated page for managing diabetes types
 * Linked from Settings. All dropdown models use this table.
 */
$page_title = '🩸 إدارة أنواع السكري | Diabetes Types';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Location: ' . BASE_URL . '/modules/dashboard.php?error=unauthorized');
    exit;
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['type_id'] ?? 0);

        if ($action === 'add') {
            $stmt = $mysqli->prepare("INSERT INTO diabetes_types (type_name_ar, type_name_en, sort_order) VALUES (?, ?, ?)");
            $sort = (int)($_POST['sort_order'] ?? 0);
            $stmt->bind_param('ssi', 
                sanitize_input($_POST['type_name_ar']),
                sanitize_input($_POST['type_name_en'] ?? ''),
                $sort
            );
            if ($stmt->execute()) {
                $message = '✅ تم إضافة نوع السكري بنجاح';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }

        elseif ($action === 'edit') {
            $stmt = $mysqli->prepare("UPDATE diabetes_types SET type_name_ar=?, type_name_en=?, sort_order=?, is_active=? WHERE type_id=?");
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort = (int)($_POST['sort_order'] ?? 0);
            $stmt->bind_param('ssiii',
                sanitize_input($_POST['type_name_ar']),
                sanitize_input($_POST['type_name_en'] ?? ''),
                $sort,
                $is_active,
                $id
            );
            if ($stmt->execute()) {
                $message = '✅ تم تحديث نوع السكري';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }

        elseif ($action === 'toggle') {
            $current = $mysqli->query("SELECT is_active FROM diabetes_types WHERE type_id = $id")->fetch_assoc()['is_active'];
            $new = $current ? 0 : 1;
            $mysqli->query("UPDATE diabetes_types SET is_active = $new WHERE type_id = $id");
            $message = $new ? '✅ تم التفعيل' : '⏸️ تم التعطيل';
            $message_type = 'success';
        }

        elseif ($action === 'delete') {
            $mysqli->query("DELETE FROM diabetes_types WHERE type_id = $id");
            $message = '✅ تم الحذف';
            $message_type = 'success';
        }
    }
}

// Get all diabetes types
$types = $mysqli->query("SELECT * FROM diabetes_types ORDER BY sort_order ASC, type_name_ar ASC");

// Get editing item
$edit_type = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_type = $mysqli->query("SELECT * FROM diabetes_types WHERE type_id = $eid")->fetch_assoc();
}

// Stats
$total_types = $mysqli->query("SELECT COUNT(*) as cnt FROM diabetes_types")->fetch_assoc()['cnt'];
$active_types = $mysqli->query("SELECT COUNT(*) as cnt FROM diabetes_types WHERE is_active = 1")->fetch_assoc()['cnt'];
$used_in_patients = $mysqli->query("SELECT COUNT(DISTINCT diabetes_type) as cnt FROM medical_history")->fetch_assoc()['cnt'];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="page-title">🩸 إدارة أنواع السكري</h1>
        <p class="page-subtitle">إدارة وتحديث أنواع السكري التي تظهر في جميع قوائم النظام المنسدلة</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/modules/settings/lookup_management.php" class="btn btn-secondary">🔙 إدارة القوائم</a>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-value" style="color:var(--teal);"><?php echo $total_types; ?></div>
        <div class="stat-label">إجمالي الأنواع</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--green);"><?php echo $active_types; ?></div>
        <div class="stat-label">الأنواع النشطة</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--blue);"><?php echo $used_in_patients; ?></div>
        <div class="stat-label">مستخدمة في المرضى</div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="flex flex-wrap gap-4">
    <!-- Add/Edit Form -->
    <div class="card" style="flex:1;min-width:350px;">
        <div class="card-header">
            <div class="card-title"><?php echo $edit_type ? '✏️ تعديل نوع سكري' : '➕ إضافة نوع سكري جديد'; ?></div>
        </div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $edit_type ? 'edit' : 'add'; ?>">
            <?php if ($edit_type): ?>
                <input type="hidden" name="type_id" value="<?php echo $edit_type['type_id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="field col-span-2">
                    <label>الاسم (عربي) <span class="required">*</span></label>
                    <input type="text" name="type_name_ar" required 
                           value="<?php echo $edit_type ? escape_output($edit_type['type_name_ar']) : ''; ?>"
                           placeholder="مثال: النوع الأول (Type 1)">
                </div>
                <div class="field">
                    <label>الاسم (إنجليزي)</label>
                    <input type="text" name="type_name_en" 
                           value="<?php echo $edit_type ? escape_output($edit_type['type_name_en'] ?? '') : ''; ?>"
                           placeholder="Type 1 Diabetes">
                </div>
                <div class="field">
                    <label>ترتيب العرض</label>
                    <input type="number" name="sort_order" min="0" 
                           value="<?php echo $edit_type ? $edit_type['sort_order'] : '0'; ?>">
                    <small style="color:#94a3b8;font-size:11px;">الأقل يظهر أولاً في القوائم</small>
                </div>
            </div>

            <?php if ($edit_type): ?>
            <div class="field mt-3">
                <label class="check-item">
                    <input type="checkbox" name="is_active" value="1" <?php echo $edit_type['is_active'] ? 'checked' : ''; ?>>
                    <span>نشط — يظهر في القوائم</span>
                </label>
            </div>
            <?php endif; ?>

            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_type ? '💾 حفظ التغييرات' : '💾 إضافة'; ?>
                </button>
                <?php if ($edit_type): ?>
                    <a href="?" class="btn btn-secondary">❌ إلغاء</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Types List -->
    <div class="card" style="flex:1;min-width:350px;">
        <div class="card-header">
            <div class="card-title">📋 قائمة أنواع السكري</div>
            <span class="badge badge-info"><?php echo $total_types; ?> نوع</span>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم (عربي)</th>
                        <th>English</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($types->num_rows === 0): ?>
                    <tr><td colspan="6" style="color:#94a3b8;padding:30px;">لا توجد أنواع سكري مضافة بعد</td></tr>
                    <?php else: while ($t = $types->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $t['type_id']; ?></td>
                        <td style="font-weight:700;">🩸 <?php echo escape_output($t['type_name_ar']); ?></td>
                        <td><span style="color:#94a3b8;"><?php echo escape_output($t['type_name_en'] ?? '—'); ?></span></td>
                        <td><?php echo $t['sort_order']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $t['is_active'] ? 'نشط' : 'معطل'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?edit=<?php echo $t['type_id']; ?>" class="btn btn-sm btn-primary">✏️</a>
                            <form method="post" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="type_id" value="<?php echo $t['type_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"><?php echo $t['is_active'] ? '⏸️' : '▶️'; ?></button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف نوع السكري هذا؟')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type_id" value="<?php echo $t['type_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Info Card -->
<div class="card mt-4" style="background:var(--teal-pale);border:1px solid var(--teal-mid);">
    <div class="flex items-center gap-3">
        <span style="font-size:32px;">💡</span>
        <div>
            <h4 style="color:var(--teal);font-family:'Cairo',sans-serif;font-size:15px;">كيف تعمل أنواع السكري في النظام</h4>
            <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">
                أنواع السكري المضافة هنا تظهر تلقائياً في جميع قوائم النظام: 
                <strong>التاريخ الطبي</strong> للمريض، 
                <strong>التعليمات التلقائية</strong>، 
                <strong>التقارير</strong>، و<strong>لوحة التحكم</strong>.
                يمكنك تعطيل أي نوع بدلاً من حذفه للحفاظ على سلامة بيانات المرضى المرتبطة به.
            </p>
        </div>
    </div>
</div>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
