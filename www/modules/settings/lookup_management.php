<?php
/**
 * Lookup Management — Settings for Cities, Diabetes Types, and Generic Dropdowns
 */
$page_title = '🔧 إدارة القوائم | Lookup Management';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Location: ' . BASE_URL . '/modules/dashboard.php?error=unauthorized');
    exit;
}

$tab = $_GET['tab'] ?? 'cities';
$message = '';
$message_type = '';

function handle_crud($table, $id_field, $fields, $mysqli) {
    // Whitelist allowed tables to prevent SQL injection
    $allowed_tables = ['cities' => 'city_id', 'diabetes_types' => 'type_id', 'lookup_options' => 'option_id'];
    $allowed_fields = ['city_name_ar', 'city_name_en', 'type_name_ar', 'type_name_en', 'sort_order', 'category', 'option_value_ar', 'option_value_en'];
    
    if (!isset($allowed_tables[$table])) return ['❌ جدول غير مسموح', 'danger'];
    if ($id_field !== $allowed_tables[$table]) return ['❌ حقل غير صالح', 'danger'];
    foreach ($fields as $f) {
        if (!in_array($f, $allowed_fields)) return ['❌ حقل غير مسموح', 'danger'];
    }
    
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST[$id_field] ?? 0);
    
    if ($action === 'add') {
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $names = implode(', ', $fields);
        $types = '';
        $values = [];
        foreach ($fields as $f) {
            $types .= 's';
            $values[] = sanitize_input($_POST[$f] ?? '');
        }
        $stmt = $mysqli->prepare("INSERT INTO $table ($names) VALUES ($placeholders)");
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) return ['✅ تمت الإضافة بنجاح', 'success'];
        else return ['❌ خطأ: ' . $stmt->error, 'danger'];
    }
    
    if ($action === 'edit' && $id) {
        $sets = implode('=?, ', $fields) . '=?';
        $types = '';
        $values = [];
        foreach ($fields as $f) {
            $types .= 's';
            $values[] = sanitize_input($_POST[$f] ?? '');
        }
        $types .= 'i';
        $values[] = $id;
        $stmt = $mysqli->prepare("UPDATE $table SET $sets WHERE $id_field = ?");
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) return ['✅ تم التحديث بنجاح', 'success'];
        else return ['❌ خطأ: ' . $stmt->error, 'danger'];
    }
    
    if ($action === 'toggle' && $id) {
        $current = $mysqli->query("SELECT is_active FROM $table WHERE $id_field = $id")->fetch_assoc()['is_active'];
        $new = $current ? 0 : 1;
        $mysqli->query("UPDATE $table SET is_active = $new WHERE $id_field = $id");
        return [$new ? '✅ تم التفعيل' : '⏸️ تم التعطيل', 'success'];
    }
    
    if ($action === 'delete' && $id) {
        $stmt = $mysqli->prepare("DELETE FROM $table WHERE $id_field = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) return ['✅ تم الحذف', 'success'];
        else return ['❌ لا يمكن حذف هذا العنصر', 'danger'];
    }
    
    return [null, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        if ($tab === 'cities') {
            list($msg, $type) = handle_crud('cities', 'city_id', ['city_name_ar', 'city_name_en'], $mysqli);
        } elseif ($tab === 'diabetes_types') {
            list($msg, $type) = handle_crud('diabetes_types', 'type_id', ['type_name_ar', 'type_name_en', 'sort_order'], $mysqli);
        } elseif ($tab === 'lookup') {
            list($msg, $type) = handle_crud('lookup_options', 'option_id', ['category', 'option_value_ar', 'option_value_en'], $mysqli);
        }
        if ($msg) { $message = $msg; $message_type = $type; }
    }
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🔧 إدارة القوائم</h1>
    <p class="page-subtitle">إدارة المدن، أنواع السكري، وخيارات القوائم المنسدلة</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="flex gap-2 mb-4 flex-wrap">
    <a href="?tab=cities" class="btn <?php echo $tab === 'cities' ? 'btn-primary' : 'btn-secondary'; ?>">🏙️ المدن</a>
    <a href="?tab=diabetes_types" class="btn <?php echo $tab === 'diabetes_types' ? 'btn-primary' : 'btn-secondary'; ?>">🩸 أنواع السكري</a>
    <a href="?tab=lookup" class="btn <?php echo $tab === 'lookup' ? 'btn-primary' : 'btn-secondary'; ?>">📋 خيارات القوائم</a>
</div>

<?php
// =========== CITIES TAB ===========
if ($tab === 'cities'):
    $items = $mysqli->query("SELECT * FROM cities ORDER BY city_name_ar ASC");
?>
<div class="card">
    <div class="card-header">
        <div class="card-title">🏙️ إدارة المدن</div>
    </div>
    
    <!-- Add Form -->
    <form method="post" class="mb-4 flex gap-2" style="align-items:end;flex-wrap:wrap;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="field" style="flex:2;min-width:150px;">
            <label>الاسم (عربي)</label>
            <input type="text" name="city_name_ar" required placeholder="مثال: الرياض">
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>English</label>
            <input type="text" name="city_name_en" placeholder="Riyadh">
        </div>
        <button type="submit" class="btn btn-primary">➕ إضافة</button>
    </form>
    
    <div class="table-container">
        <table>
            <thead><tr><th>#</th><th>الاسم (عربي)</th><th>English</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $item['city_id']; ?></td>
                    <td><?php echo escape_output($item['city_name_ar']); ?></td>
                    <td><?php echo escape_output($item['city_name_en'] ?? '—'); ?></td>
                    <td><span class="badge badge-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $item['is_active'] ? 'نشط' : 'معطل'; ?></span></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="city_id" value="<?php echo $item['city_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?php echo $item['is_active'] ? '⏸️' : '▶️'; ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('حذف هذه المدينة؟')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="city_id" value="<?php echo $item['city_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// =========== DIABETES TYPES TAB ===========
elseif ($tab === 'diabetes_types'):
    $items = $mysqli->query("SELECT * FROM diabetes_types ORDER BY sort_order ASC, type_name_ar ASC");
?>
<div class="card">
    <div class="card-header">
        <div class="card-title">🩸 إدارة أنواع السكري</div>
    </div>
    
    <form method="post" class="mb-4 flex gap-2" style="align-items:end;flex-wrap:wrap;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="field" style="flex:2;min-width:150px;">
            <label>الاسم (عربي)</label>
            <input type="text" name="type_name_ar" required placeholder="مثال: النوع الأول (Type 1)">
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>English</label>
            <input type="text" name="type_name_en" placeholder="Type 1 Diabetes">
        </div>
        <div class="field" style="flex:0.5;min-width:60px;">
            <label>الترتيب</label>
            <input type="number" name="sort_order" value="1" min="0" style="width:60px;">
        </div>
        <button type="submit" class="btn btn-primary">➕ إضافة</button>
    </form>
    
    <div class="table-container">
        <table>
            <thead><tr><th>#</th><th>الاسم (عربي)</th><th>English</th><th>الترتيب</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $item['type_id']; ?></td>
                    <td><?php echo escape_output($item['type_name_ar']); ?></td>
                    <td><?php echo escape_output($item['type_name_en'] ?? '—'); ?></td>
                    <td><?php echo $item['sort_order']; ?></td>
                    <td><span class="badge badge-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $item['is_active'] ? 'نشط' : 'معطل'; ?></span></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="type_id" value="<?php echo $item['type_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?php echo $item['is_active'] ? '⏸️' : '▶️'; ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('حذف؟')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="type_id" value="<?php echo $item['type_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// =========== LOOKUP OPTIONS TAB ===========
elseif ($tab === 'lookup'):
    $categories = $mysqli->query("SELECT DISTINCT category FROM lookup_options ORDER BY category ASC");
    $selected_cat = $_GET['cat'] ?? '';
    $cat_filter = $selected_cat ? "WHERE category = '$selected_cat'" : '';
    $items = $mysqli->query("SELECT * FROM lookup_options $cat_filter ORDER BY category ASC, sort_order ASC, option_value_ar ASC");
?>
<div class="card">
    <div class="card-header">
        <div class="card-title">📋 إدارة خيارات القوائم</div>
    </div>
    
    <!-- Category filter & add -->
    <div class="flex gap-2 mb-4 flex-wrap">
        <a href="?tab=lookup" class="btn btn-sm <?php echo !$selected_cat ? 'btn-primary' : 'btn-secondary'; ?>">الكل</a>
        <?php while ($cat = $categories->fetch_assoc()): ?>
            <a href="?tab=lookup&cat=<?php echo urlencode($cat['category']); ?>" class="btn btn-sm <?php echo $selected_cat === $cat['category'] ? 'btn-primary' : 'btn-secondary'; ?>">
                <?php echo escape_output($cat['category']); ?>
            </a>
        <?php endwhile; ?>
    </div>
    
    <form method="post" class="mb-4 flex gap-2" style="align-items:end;flex-wrap:wrap;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="field" style="flex:1;min-width:120px;">
            <label>التصنيف</label>
            <input type="text" name="category" required placeholder="مثال: marital_status" value="<?php echo escape_output($selected_cat); ?>">
        </div>
        <div class="field" style="flex:1.5;min-width:150px;">
            <label>القيمة (عربي)</label>
            <input type="text" name="option_value_ar" required placeholder="القيمة بالعربية">
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>English</label>
            <input type="text" name="option_value_en" placeholder="English value">
        </div>
        <button type="submit" class="btn btn-primary">➕ إضافة</button>
    </form>
    
    <div class="table-container">
        <table>
            <thead><tr><th>#</th><th>التصنيف</th><th>القيمة (عربي)</th><th>English</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $item['option_id']; ?></td>
                    <td><span class="badge badge-info"><?php echo escape_output($item['category']); ?></span></td>
                    <td><?php echo escape_output($item['option_value_ar']); ?></td>
                    <td><?php echo escape_output($item['option_value_en'] ?? '—'); ?></td>
                    <td><span class="badge badge-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $item['is_active'] ? 'نشط' : 'معطل'; ?></span></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="option_id" value="<?php echo $item['option_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?php echo $item['is_active'] ? '⏸️' : '▶️'; ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('حذف هذا الخيار؟')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="option_id" value="<?php echo $item['option_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
