<?php
/**
 * Auto Instructions Management — Task 6
 * Create and manage pre-defined instructions based on patient criteria
 */
$page_title = '📋 التعليمات التلقائية | Auto Instructions';
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
        
        if ($action === 'add' || $action === 'edit') {
            $id = (int)($_POST['instruction_id'] ?? 0);
            $title = sanitize_input($_POST['title_ar']);
            $content = sanitize_input($_POST['content_ar']);
            $category = sanitize_input($_POST['category']);
            $diabetes_type = sanitize_input($_POST['diabetes_type'] ?? '');
            $min_hba1c = $_POST['min_hba1c'] !== '' ? (float)$_POST['min_hba1c'] : null;
            $max_hba1c = $_POST['max_hba1c'] !== '' ? (float)$_POST['max_hba1c'] : null;
            $min_wagner = $_POST['min_wagner'] !== '' ? (int)$_POST['min_wagner'] : null;
            $max_wagner = $_POST['max_wagner'] !== '' ? (int)$_POST['max_wagner'] : null;
            $has_wound = isset($_POST['has_wound']) ? ($_POST['has_wound'] !== '' ? (int)$_POST['has_wound'] : null) : null;
            $smoking = sanitize_input($_POST['smoking_status'] ?? '');
            
            if ($action === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO auto_instructions (title_ar, content_ar, category, diabetes_type, min_hba1c, max_hba1c, min_wagner, max_wagner, has_wound, smoking_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssddddds', $title, $content, $category, $diabetes_type ?: null, $min_hba1c, $max_hba1c, $min_wagner, $max_wagner, $has_wound, $smoking ?: null);
            } else {
                $stmt = $mysqli->prepare("UPDATE auto_instructions SET title_ar=?, content_ar=?, category=?, diabetes_type=?, min_hba1c=?, max_hba1c=?, min_wagner=?, max_wagner=?, has_wound=?, smoking_status=? WHERE instruction_id=?");
                $stmt->bind_param('ssssddddddsi', $title, $content, $category, $diabetes_type ?: null, $min_hba1c, $max_hba1c, $min_wagner, $max_wagner, $has_wound, $smoking ?: null, $id);
            }
            
            if ($stmt->execute()) {
                $message = $action === 'add' ? '✅ تم إضافة التعليمات' : '✅ تم تحديث التعليمات';
                $message_type = 'success';
            } else { $message = '❌ خطأ: ' . $stmt->error; $message_type = 'danger'; }
        }
        
        elseif ($action === 'toggle') {
            $id = (int)$_POST['instruction_id'];
            $current = $mysqli->query("SELECT is_active FROM auto_instructions WHERE instruction_id = $id")->fetch_assoc()['is_active'];
            $new = $current ? 0 : 1;
            $mysqli->query("UPDATE auto_instructions SET is_active = $new WHERE instruction_id = $id");
            $message = $new ? '✅ تم التفعيل' : '⏸️ تم التعطيل';
            $message_type = 'success';
        }
        
        elseif ($action === 'delete') {
            $id = (int)$_POST['instruction_id'];
            $mysqli->query("DELETE FROM auto_instructions WHERE instruction_id = $id");
            $message = '✅ تم الحذف';
            $message_type = 'success';
        }
    }
}

$instructions = $mysqli->query("SELECT * FROM auto_instructions ORDER BY category ASC, sort_order ASC, title_ar ASC");
$edit_inst = isset($_GET['edit']) ? $mysqli->query("SELECT * FROM auto_instructions WHERE instruction_id = " . (int)$_GET['edit'])->fetch_assoc() : null;
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">📋 إدارة التعليمات التلقائية</h1>
    <p class="page-subtitle">تعليمات تُولد تلقائياً للمريض حسب حالته — نوع السكر، الفحوصات، الجروح</p>
</div>

<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div><?php endif; ?>

<div class="flex flex-wrap gap-4">
    <!-- Add/Edit Form -->
    <div class="card" style="flex:1;min-width:380px;">
        <div class="card-header">
            <div class="card-title"><?php echo $edit_inst ? '✏️ تعديل تعليمات' : '➕ إضافة تعليمات جديدة'; ?></div>
        </div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $edit_inst ? 'edit' : 'add'; ?>">
            <?php if ($edit_inst): ?><input type="hidden" name="instruction_id" value="<?php echo $edit_inst['instruction_id']; ?>"><?php endif; ?>
            
            <div class="form-grid">
                <div class="field col-span-2">
                    <label>العنوان <span class="required">*</span></label>
                    <input type="text" name="title_ar" required value="<?php echo $edit_inst ? escape_output($edit_inst['title_ar']) : ''; ?>" placeholder="عنوان التعليمات">
                </div>
                <div class="field">
                    <label>التصنيف <span class="required">*</span></label>
                    <select name="category" required>
                        <?php foreach (['عام', 'تغذية', 'عناية قدم', 'أدوية', 'طوارئ', 'تمارين'] as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $edit_inst && $edit_inst['category'] === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field col-span-2">
                    <label>المحتوى <span class="required">*</span></label>
                    <textarea name="content_ar" required rows="6" placeholder="نص التعليمات..."><?php echo $edit_inst ? escape_output($edit_inst['content_ar']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Matching Criteria -->
            <div class="form-section mt-3">
                <div class="form-section-title">🎯 معايير التطبيق (اختياري — اترك فارغاً للتطبيق على الكل)</div>
                <div class="form-grid">
                    <div class="field">
                        <label>نوع السكري</label>
                        <select name="diabetes_type">
                            <option value="">الكل</option>
                            <?php 
                            $dtypes = $mysqli->query("SELECT type_name_ar FROM diabetes_types WHERE is_active = 1 ORDER BY sort_order");
                            while ($dt = $dtypes->fetch_assoc()): 
                            ?>
                            <option value="<?php echo escape_output($dt['type_name_ar']); ?>" <?php echo $edit_inst && $edit_inst['diabetes_type'] === $dt['type_name_ar'] ? 'selected' : ''; ?>>
                                <?php echo escape_output($dt['type_name_ar']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>حالة التدخين</label>
                        <select name="smoking_status">
                            <option value="">الكل</option>
                            <option value="مدخن" <?php echo $edit_inst && $edit_inst['smoking_status'] === 'مدخن' ? 'selected' : ''; ?>>مدخن</option>
                            <option value="لا" <?php echo $edit_inst && $edit_inst['smoking_status'] === 'لا' ? 'selected' : ''; ?>>غير مدخن</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>الحد الأدنى HbA1c</label>
                        <input type="number" name="min_hba1c" step="0.1" value="<?php echo $edit_inst ? $edit_inst['min_hba1c'] : ''; ?>" placeholder="مثال: 7">
                    </div>
                    <div class="field">
                        <label>الحد الأقصى HbA1c</label>
                        <input type="number" name="max_hba1c" step="0.1" value="<?php echo $edit_inst ? $edit_inst['max_hba1c'] : ''; ?>" placeholder="مثال: 10">
                    </div>
                    <div class="field">
                        <label>أدنى درجة Wagner</label>
                        <input type="number" name="min_wagner" min="0" max="5" value="<?php echo $edit_inst ? $edit_inst['min_wagner'] : ''; ?>">
                    </div>
                    <div class="field">
                        <label>أقصى درجة Wagner</label>
                        <input type="number" name="max_wagner" min="0" max="5" value="<?php echo $edit_inst ? $edit_inst['max_wagner'] : ''; ?>">
                    </div>
                    <div class="field">
                        <label>وجود جرح</label>
                        <select name="has_wound">
                            <option value="">الكل</option>
                            <option value="1" <?php echo $edit_inst && $edit_inst['has_wound'] === 1 ? 'selected' : ''; ?>>يوجد جرح</option>
                            <option value="0" <?php echo $edit_inst && $edit_inst['has_wound'] === 0 ? 'selected' : ''; ?>>لا يوجد جرح</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($edit_inst): ?>
            <div class="field mt-2"><label class="check-item"><input type="checkbox" name="is_active" <?php echo $edit_inst['is_active'] ? 'checked' : ''; ?>> <span>نشط</span></label></div>
            <?php endif; ?>
            
            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><?php echo $edit_inst ? '💾 حفظ' : '💾 إضافة'; ?></button>
                <?php if ($edit_inst): ?><a href="?" class="btn btn-secondary">❌ إلغاء</a><?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Instructions List -->
    <div class="card" style="flex:1;min-width:350px;">
        <div class="card-header"><div class="card-title">📋 قائمة التعليمات</div></div>
        <?php if ($instructions->num_rows === 0): ?>
        <p style="color:#94a3b8;text-align:center;padding:20px;">لا توجد تعليمات بعد</p>
        <?php else: ?>
            <?php 
            $current_cat = '';
            while ($inst = $instructions->fetch_assoc()): 
                if ($inst['category'] !== $current_cat):
                    $current_cat = $inst['category'];
            ?>
            <div class="mt-3 mb-2">
                <span class="badge badge-info" style="font-size:13px;padding:4px 14px;">
                    📂 <?php echo escape_output($current_cat); ?>
                </span>
            </div>
            <?php endif; ?>
            <div style="padding:10px;margin-bottom:6px;border:1px solid var(--border);border-radius:10px;border-right:3px solid <?php echo $inst['is_active'] ? 'var(--teal)' : 'var(--text-light)'; ?>;">
                <div class="flex justify-between items-center">
                    <div>
                        <strong style="font-size:14px;"><?php echo escape_output($inst['title_ar']); ?></strong>
                        <?php 
                        $criteria = [];
                        if ($inst['diabetes_type']) $criteria[] = '🩸 ' . escape_output($inst['diabetes_type']);
                        if ($inst['min_hba1c'] !== null || $inst['max_hba1c'] !== null) $criteria[] = '📊 HbA1c: ' . ($inst['min_hba1c'] ?? '0') . '-' . ($inst['max_hba1c'] ?? '∞');
                        if ($inst['has_wound'] !== null) $criteria[] = $inst['has_wound'] ? '🩹 جرح' : '✅ بدون جرح';
                        if ($criteria):
                        ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            <?php echo implode(' | ', $criteria); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-1">
                        <a href="?edit=<?php echo $inst['instruction_id']; ?>" class="btn btn-sm btn-primary">✏️</a>
                        <form method="post" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="instruction_id" value="<?php echo $inst['instruction_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?php echo $inst['is_active'] ? '⏸️' : '▶️'; ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('حذف؟')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="instruction_id" value="<?php echo $inst['instruction_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </div>
                </div>
                <div style="margin-top:6px;font-size:13px;color:var(--text-muted);white-space:pre-line;max-height:80px;overflow:hidden;">
                    <?php echo escape_output(mb_substr($inst['content_ar'], 0, 200)); ?>
                    <?php if (mb_strlen($inst['content_ar']) > 200): ?>...<?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
