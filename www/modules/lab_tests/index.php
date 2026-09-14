<?php
/**
 * Lab Tests Catalog — Task 2
 * Manage test types with categories and normal ranges
 */
$page_title = '🧪 إدارة الفحوصات | Lab Tests';
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'admin', 'doctor']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_test') {
            $stmt = $mysqli->prepare("INSERT INTO lab_test_types (category_id, name_ar, name_en, abbreviation, unit, normal_min, normal_max, normal_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssddds', 
                $_POST['category_id'] ? (int)$_POST['category_id'] : null,
                sanitize_input($_POST['name_ar']),
                sanitize_input($_POST['name_en'] ?? ''),
                sanitize_input($_POST['abbreviation'] ?? ''),
                sanitize_input($_POST['unit'] ?? ''),
                $_POST['normal_min'] ? (float)$_POST['normal_min'] : null,
                $_POST['normal_max'] ? (float)$_POST['normal_max'] : null,
                sanitize_input($_POST['normal_text'] ?? '')
            );
            if ($stmt->execute()) {
                $message = '✅ تم إضافة الفحص';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }
        
        elseif ($action === 'edit_test') {
            $stmt = $mysqli->prepare("UPDATE lab_test_types SET category_id=?, name_ar=?, name_en=?, abbreviation=?, unit=?, normal_min=?, normal_max=?, normal_text=?, is_active=? WHERE test_id=?");
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt->bind_param('issssdddsii',
                $_POST['category_id'] ? (int)$_POST['category_id'] : null,
                sanitize_input($_POST['name_ar']),
                sanitize_input($_POST['name_en'] ?? ''),
                sanitize_input($_POST['abbreviation'] ?? ''),
                sanitize_input($_POST['unit'] ?? ''),
                $_POST['normal_min'] ? (float)$_POST['normal_min'] : null,
                $_POST['normal_max'] ? (float)$_POST['normal_max'] : null,
                sanitize_input($_POST['normal_text'] ?? ''),
                $is_active,
                (int)$_POST['test_id']
            );
            if ($stmt->execute()) {
                $message = '✅ تم تحديث الفحص';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }
        
        elseif ($action === 'add_category') {
            $stmt = $mysqli->prepare("INSERT INTO lab_test_categories (name_ar, name_en, sort_order) VALUES (?, ?, ?)");
            $sort = (int)($_POST['sort_order'] ?? 0);
            $stmt->bind_param('ssi', sanitize_input($_POST['cat_name_ar']), sanitize_input($_POST['cat_name_en'] ?? ''), $sort);
            $stmt->execute();
            $message = '✅ تم إضافة التصنيف';
            $message_type = 'success';
        }
        
        elseif ($action === 'toggle') {
            $id = (int)$_POST['test_id'];
            $current = $mysqli->query("SELECT is_active FROM lab_test_types WHERE test_id = $id")->fetch_assoc()['is_active'];
            $new = $current ? 0 : 1;
            $mysqli->query("UPDATE lab_test_types SET is_active = $new WHERE test_id = $id");
            $message = $new ? '✅ تم التفعيل' : '⏸️ تم التعطيل';
            $message_type = 'success';
        }
    }
}

// Get categories
$categories = $mysqli->query("SELECT * FROM lab_test_categories WHERE is_active = 1 ORDER BY sort_order ASC, name_ar ASC");
$cat_filter = isset($_GET['cat']) && $_GET['cat'] ? "WHERE t.category_id = " . (int)$_GET['cat'] : "WHERE 1=1";
$tests = $mysqli->query("SELECT t.*, c.name_ar as category_name FROM lab_test_types t LEFT JOIN lab_test_categories c ON t.category_id = c.category_id $cat_filter ORDER BY t.sort_order ASC, t.name_ar ASC");

$edit_test = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_test = $mysqli->query("SELECT * FROM lab_test_types WHERE test_id = $eid")->fetch_assoc();
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🧪 إدارة الفحوصات المخبرية</h1>
    <p class="page-subtitle">إنشاء وتعديل وإدارة أنواع الفحوصات مع النطاقات الطبيعية</p>
</div>

<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div><?php endif; ?>

<div class="flex flex-wrap gap-4">
    <!-- Add/Edit Test Card -->
    <div class="card" style="flex:1;min-width:350px;">
        <div class="card-header">
            <div class="card-title"><?php echo $edit_test ? '✏️ تعديل فحص' : '➕ إضافة فحص جديد'; ?></div>
        </div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $edit_test ? 'edit_test' : 'add_test'; ?>">
            <?php if ($edit_test): ?><input type="hidden" name="test_id" value="<?php echo $edit_test['test_id']; ?>"><?php endif; ?>
            
            <div class="form-grid">
                <div class="field col-span-2">
                    <label>اسم الفحص (عربي) <span class="required">*</span></label>
                    <input type="text" name="name_ar" required value="<?php echo $edit_test ? escape_output($edit_test['name_ar']) : ''; ?>">
                </div>
                <div class="field">
                    <label>التصنيف</label>
                    <select name="category_id">
                        <option value="">-- اختر --</option>
                        <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $edit_test && $edit_test['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo escape_output($cat['name_ar']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field">
                    <label>الاختصار</label>
                    <input type="text" name="abbreviation" value="<?php echo $edit_test ? escape_output($edit_test['abbreviation'] ?? '') : ''; ?>" placeholder="HbA1c">
                </div>
                <div class="field">
                    <label>English</label>
                    <input type="text" name="name_en" value="<?php echo $edit_test ? escape_output($edit_test['name_en'] ?? '') : ''; ?>">
                </div>
                <div class="field">
                    <label>الوحدة</label>
                    <input type="text" name="unit" value="<?php echo $edit_test ? escape_output($edit_test['unit'] ?? '') : ''; ?>" placeholder="mg/dL">
                </div>
                <div class="field">
                    <label>الحد الأدنى الطبيعي</label>
                    <input type="number" name="normal_min" step="0.01" value="<?php echo $edit_test ? $edit_test['normal_min'] : ''; ?>">
                </div>
                <div class="field">
                    <label>الحد الأقصى الطبيعي</label>
                    <input type="number" name="normal_max" step="0.01" value="<?php echo $edit_test ? $edit_test['normal_max'] : ''; ?>">
                </div>
                <div class="field col-span-2">
                    <label>نص توضيحي للنطاق الطبيعي</label>
                    <input type="text" name="normal_text" value="<?php echo $edit_test ? escape_output($edit_test['normal_text'] ?? '') : ''; ?>" placeholder="مثال: أقل من 100 mg/dL طبيعي">
                </div>
            </div>
            
            <?php if ($edit_test): ?>
                <div class="field mt-2"><label class="check-item"><input type="checkbox" name="is_active" <?php echo $edit_test['is_active'] ? 'checked' : ''; ?>> <span>نشط</span></label></div>
            <?php endif; ?>
            
            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><?php echo $edit_test ? '💾 حفظ' : '💾 إضافة'; ?></button>
                <?php if ($edit_test): ?><a href="?cat=<?php echo (int)($_GET['cat'] ?? 0); ?>" class="btn btn-secondary">❌ إلغاء</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Categories -->
    <div class="card" style="flex:0.5;min-width:200px;">
        <div class="card-header"><div class="card-title">📂 التصنيفات</div></div>
        <form method="post" class="flex gap-2" style="align-items:end;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_category">
            <div class="field" style="flex:1;"><label>إضافة</label><input type="text" name="cat_name_ar" required placeholder="اسم التصنيف"></div>
            <button type="submit" class="btn btn-sm btn-primary">➕</button>
        </form>
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:4px;">
            <a href="?" class="btn btn-sm <?php echo !isset($_GET['cat']) || !$_GET['cat'] ? 'btn-primary' : 'btn-secondary'; ?>" style="justify-content:flex-start;">📋 الكل</a>
            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                <a href="?cat=<?php echo $cat['category_id']; ?>" class="btn btn-sm <?php echo (int)($_GET['cat'] ?? 0) === $cat['category_id'] ? 'btn-primary' : 'btn-secondary'; ?> style="justify-content:flex-start;">
                    📂 <?php echo escape_output($cat['name_ar']); ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Tests Table -->
<div class="card mt-4">
    <div class="card-header"><div class="card-title">🧪 قائمة الفحوصات</div></div>
    <div class="table-container">
        <table>
            <thead><tr>
                <th>#</th><th>الاسم</th><th>التصنيف</th><th>الاختصار</th><th>الوحدة</th>
                <th>النطاق الطبيعي</th><th>الحالة</th><th>إجراءات</th>
            </tr></thead>
            <tbody>
                <?php if ($tests->num_rows === 0): ?>
                <tr><td colspan="8" style="color:#94a3b8;">لا توجد فحوصات</td></tr>
                <?php else: while ($t = $tests->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $t['test_id']; ?></td>
                    <td style="font-weight:700;"><?php echo escape_output($t['name_ar']); ?></td>
                    <td><span class="badge badge-info"><?php echo escape_output($t['category_name'] ?? '—'); ?></span></td>
                    <td><?php echo escape_output($t['abbreviation'] ?? '—'); ?></td>
                    <td><?php echo $t['unit'] ?: '—'; ?></td>
                    <td>
                        <?php if ($t['normal_min'] !== null || $t['normal_max'] !== null): ?>
                            <?php echo $t['normal_min'] ?? '—'; ?> – <?php echo $t['normal_max'] ?? '—'; ?>
                            <?php if ($t['normal_text']): ?><br><small style="color:#94a3b8;"><?php echo escape_output($t['normal_text']); ?></small><?php endif; ?>
                        <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $t['is_active'] ? 'نشط' : 'معطل'; ?></span></td>
                    <td>
                        <a href="?edit=<?php echo $t['test_id']; ?>&cat=<?php echo (int)($_GET['cat'] ?? 0); ?>" class="btn btn-sm btn-primary">✏️</a>
                        <form method="post" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="test_id" value="<?php echo $t['test_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?php echo $t['is_active'] ? '⏸️' : '▶️'; ?></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
