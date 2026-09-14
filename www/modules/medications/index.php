<?php
/**
 * Medications Catalog — Task 3
 * Manage medication catalog with categories
 */
$page_title = '💊 الأدوية | Medications';
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'admin', 'doctor']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$message = ''; $message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_medication') {
            $stmt = $mysqli->prepare("INSERT INTO medications (category_id, name_ar, name_en, active_ingredient, dosage_form, strength, unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssss', 
                $_POST['category_id'] ? (int)$_POST['category_id'] : null,
                sanitize_input($_POST['name_ar']),
                sanitize_input($_POST['name_en'] ?? ''),
                sanitize_input($_POST['active_ingredient'] ?? ''),
                sanitize_input($_POST['dosage_form'] ?? ''),
                sanitize_input($_POST['strength'] ?? ''),
                sanitize_input($_POST['unit'] ?? '')
            );
            if ($stmt->execute()) {
                $message = '✅ تم إضافة الدواء بنجاح';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }
        
        elseif ($action === 'edit_medication') {
            $stmt = $mysqli->prepare("UPDATE medications SET category_id=?, name_ar=?, name_en=?, active_ingredient=?, dosage_form=?, strength=?, unit=?, is_active=? WHERE medication_id=?");
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $stmt->bind_param('issssssii',
                $_POST['category_id'] ? (int)$_POST['category_id'] : null,
                sanitize_input($_POST['name_ar']),
                sanitize_input($_POST['name_en'] ?? ''),
                sanitize_input($_POST['active_ingredient'] ?? ''),
                sanitize_input($_POST['dosage_form'] ?? ''),
                sanitize_input($_POST['strength'] ?? ''),
                sanitize_input($_POST['unit'] ?? ''),
                $is_active,
                (int)$_POST['medication_id']
            );
            if ($stmt->execute()) {
                $message = '✅ تم تحديث الدواء';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }
        
        elseif ($action === 'toggle') {
            $id = (int)$_POST['medication_id'];
            $current = $mysqli->query("SELECT is_active FROM medications WHERE medication_id = $id")->fetch_assoc()['is_active'];
            $new = $current ? 0 : 1;
            $mysqli->query("UPDATE medications SET is_active = $new WHERE medication_id = $id");
            $message = $new ? '✅ تم التفعيل' : '⏸️ تم التعطيل';
            $message_type = 'success';
        }
        
        elseif ($action === 'delete') {
            $id = (int)$_POST['medication_id'];
            $mysqli->query("DELETE FROM medications WHERE medication_id = $id");
            $message = '✅ تم الحذف';
            $message_type = 'success';
        }
        
        elseif ($action === 'add_category') {
            $stmt = $mysqli->prepare("INSERT INTO medication_categories (name_ar, name_en) VALUES (?, ?)");
            $stmt->bind_param('ss', sanitize_input($_POST['cat_name_ar']), sanitize_input($_POST['cat_name_en'] ?? ''));
            $stmt->execute();
            $message = '✅ تم إضافة التصنيف';
            $message_type = 'success';
        }
    }
}

// Get categories
$categories = $mysqli->query("SELECT * FROM medication_categories WHERE is_active = 1 ORDER BY name_ar ASC");

// Get medications with category names
$cat_filter = isset($_GET['cat']) && $_GET['cat'] ? "WHERE m.category_id = " . (int)$_GET['cat'] : "WHERE 1=1";
$search_q = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
if ($search_q) {
    $search_sql = " AND (m.name_ar LIKE '%$search_q%' OR m.name_en LIKE '%$search_q%' OR m.active_ingredient LIKE '%$search_q%')";
} else { $search_sql = ''; }

$medications = $mysqli->query("
    SELECT m.*, c.name_ar as category_name
    FROM medications m
    LEFT JOIN medication_categories c ON m.category_id = c.category_id
    $cat_filter $search_sql
    ORDER BY m.name_ar ASC
");

// Get single medication for editing
$edit_med = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_med = $mysqli->query("SELECT * FROM medications WHERE medication_id = $eid")->fetch_assoc();
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">💊 إدارة الأدوية</h1>
    <p class="page-subtitle">كتالوج الأدوية — إنشاء، تعديل، حذف</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<div class="flex flex-wrap gap-4">
    <!-- Add Medication Form -->
    <div class="card" style="flex:1;min-width:320px;">
        <div class="card-header">
            <div class="card-title"><?php echo $edit_med ? '✏️ تعديل دواء' : '➕ إضافة دواء جديد'; ?></div>
        </div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $edit_med ? 'edit_medication' : 'add_medication'; ?>">
            <?php if ($edit_med): ?>
                <input type="hidden" name="medication_id" value="<?php echo $edit_med['medication_id']; ?>">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="field col-span-2">
                    <label>الاسم (عربي) <span class="required">*</span></label>
                    <input type="text" name="name_ar" required value="<?php echo $edit_med ? escape_output($edit_med['name_ar']) : ''; ?>" placeholder="اسم الدواء بالعربية">
                </div>
                <div class="field col-span-2">
                    <label>الاسم (إنجليزي)</label>
                    <input type="text" name="name_en" value="<?php echo $edit_med ? escape_output($edit_med['name_en'] ?? '') : ''; ?>" placeholder="Medication name">
                </div>
                <div class="field">
                    <label>التصنيف</label>
                    <select name="category_id">
                        <option value="">-- اختر التصنيف --</option>
                        <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $edit_med && $edit_med['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo escape_output($cat['name_ar']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field">
                    <label>المادة الفعالة</label>
                    <input type="text" name="active_ingredient" value="<?php echo $edit_med ? escape_output($edit_med['active_ingredient'] ?? '') : ''; ?>" placeholder="Active ingredient">
                </div>
                <div class="field">
                    <label>الشكل الصيدلاني</label>
                    <select name="dosage_form">
                        <option value="">-- اختر --</option>
                        <?php 
                        $forms = ['قرص', 'كبسولة', 'حقنة', 'محلول', 'مرهم', 'كريم', 'قطرة', 'بخاخ', 'لبوس', 'شريط'];
                        foreach ($forms as $f) {
                            $sel = $edit_med && $edit_med['dosage_form'] === $f ? 'selected' : '';
                            echo "<option value=\"$f\" $sel>$f</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="field">
                    <label>الجرعة</label>
                    <input type="text" name="strength" value="<?php echo $edit_med ? escape_output($edit_med['strength'] ?? '') : ''; ?>" placeholder="مثال: 500mg">
                </div>
                <div class="field">
                    <label>الوحدة</label>
                    <input type="text" name="unit" value="<?php echo $edit_med ? escape_output($edit_med['unit'] ?? '') : ''; ?>" placeholder="مثال: قرص, مل, وحدة">
                </div>
            </div>
            
            <?php if ($edit_med): ?>
                <div class="field mt-2">
                    <label class="check-item">
                        <input type="checkbox" name="is_active" value="1" <?php echo $edit_med['is_active'] ? 'checked' : ''; ?>>
                        <span>نشط</span>
                    </label>
                </div>
            <?php endif; ?>
            
            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><?php echo $edit_med ? '💾 حفظ التغييرات' : '💾 حفظ'; ?></button>
                <?php if ($edit_med): ?>
                    <a href="?cat=<?php echo (int)($_GET['cat'] ?? 0); ?>&q=<?php echo urlencode($search_q); ?>" class="btn btn-secondary">❌ إلغاء</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Category Management -->
    <div class="card" style="flex:0.5;min-width:250px;">
        <div class="card-header">
            <div class="card-title">📂 التصنيفات</div>
        </div>
        <form method="post" class="flex gap-2" style="align-items:end;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_category">
            <div class="field" style="flex:1;">
                <label>إضافة تصنيف</label>
                <input type="text" name="cat_name_ar" required placeholder="اسم التصنيف">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">➕</button>
        </form>
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:4px;">
            <a href="?" class="btn btn-sm <?php echo !isset($_GET['cat']) || !$_GET['cat'] ? 'btn-primary' : 'btn-secondary'; ?>" style="justify-content:flex-start;">
                📋 الكل
            </a>
            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                <a href="?cat=<?php echo $cat['category_id']; ?>" class="btn btn-sm <?php echo (int)($_GET['cat'] ?? 0) === $cat['category_id'] ? 'btn-primary' : 'btn-secondary'; ?>" style="justify-content:flex-start;">
                    📂 <?php echo escape_output($cat['name_ar']); ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Medications Table -->
<div class="card mt-4">
    <div class="card-header">
        <div class="card-title">💊 قائمة الأدوية</div>
        <form method="get" class="flex gap-2">
            <input type="text" name="q" value="<?php echo escape_output($search_q); ?>" placeholder="بحث..." style="border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-family:'Tajawal',sans-serif;font-size:13px;">
            <?php if (isset($_GET['cat'])): ?>
                <input type="hidden" name="cat" value="<?php echo (int)$_GET['cat']; ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-sm btn-primary">🔍 بحث</button>
        </form>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>التصنيف</th>
                    <th>المادة الفعالة</th>
                    <th>الشكل</th>
                    <th>الجرعة</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($medications->num_rows === 0): ?>
                    <tr><td colspan="8" style="color:#94a3b8;">لا توجد أدوية</td></tr>
                <?php else: ?>
                    <?php while ($med = $medications->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $med['medication_id']; ?></td>
                        <td style="font-weight:700;"><?php echo escape_output($med['name_ar']); ?>
                            <?php if ($med['name_en']): ?><br><small style="color:#94a3b8;"><?php echo escape_output($med['name_en']); ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge badge-info"><?php echo escape_output($med['category_name'] ?? '—'); ?></span></td>
                        <td><?php echo escape_output($med['active_ingredient'] ?? '—'); ?></td>
                        <td><?php echo $med['dosage_form'] ?: '—'; ?></td>
                        <td><?php echo escape_output($med['strength'] ?? '—'); ?></td>
                        <td><span class="badge badge-<?php echo $med['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $med['is_active'] ? 'نشط' : 'معطل'; ?></span></td>
                        <td>
                            <a href="?edit=<?php echo $med['medication_id']; ?>&cat=<?php echo (int)($_GET['cat'] ?? 0); ?>" class="btn btn-sm btn-primary">✏️</a>
                            <form method="post" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="medication_id" value="<?php echo $med['medication_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"><?php echo $med['is_active'] ? '⏸️' : '▶️'; ?></button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف هذا الدواء؟')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="medication_id" value="<?php echo $med['medication_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
