<?php
/**
 * Lab Test Ordering — Task 2
 * Order tests for a patient and enter results
 */
$page_title = '🧪 طلب فحوصات | Order Lab Tests';
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'admin', 'doctor', 'medical_assistant']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$message = ''; $message_type = '';

// Get patient info
$patient = null;
if ($patient_id) {
    $stmt = $mysqli->prepare("SELECT patient_id, full_name, file_number FROM patients WHERE patient_id = ?");
    $stmt->bind_param('i', $patient_id); $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
}

// Get visit info
$visit = null;
if ($visit_id) {
    $stmt = $mysqli->prepare("SELECT visit_id, visit_number, visit_date FROM visits WHERE visit_id = ?");
    $stmt->bind_param('i', $visit_id); $stmt->execute();
    $visit = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_order') {
            $pid = (int)$_POST['patient_id'];
            $vid = (int)$_POST['visit_id'] ?: null;
            $notes = sanitize_input($_POST['notes'] ?? '');
            $selected_tests = $_POST['test_ids'] ?? [];
            
            if (empty($selected_tests)) {
                $message = '❌ يرجى اختيار فحص واحد على الأقل';
                $message_type = 'danger';
            } else {
                $stmt = $mysqli->prepare("INSERT INTO lab_test_orders (patient_id, visit_id, ordered_by, notes) VALUES (?, ?, ?, ?)");
                $uid = $_SESSION['user_id'];
                $stmt->bind_param('iiis', $pid, $vid, $uid, $notes);
                $stmt->execute();
                $new_order_id = $stmt->insert_id;
                
                $item_stmt = $mysqli->prepare("INSERT INTO lab_test_order_items (order_id, test_type_id) VALUES (?, ?)");
                foreach ($selected_tests as $tid) {
                    $item_stmt->bind_param('ii', $new_order_id, $tid);
                    $item_stmt->execute();
                }
                $message = '✅ تم إنشاء طلب الفحوصات بنجاح';
                $message_type = 'success';
                $order_id = $new_order_id;
            }
        }
        
        elseif ($action === 'save_results') {
            $oid = (int)$_POST['order_id'];
            $results = $_POST['results'] ?? [];
            
            $stmt = $mysqli->prepare("UPDATE lab_test_order_items SET result_value = ?, result_flag = ?, result_date = NOW(), entered_by = ? WHERE item_id = ?");
            $uid = $_SESSION['user_id'];
            
            foreach ($results as $item_id => $data) {
                $value = sanitize_input($data['value'] ?? '');
                $flag = sanitize_input($data['flag'] ?? '');
                $stmt->bind_param('ssii', $value, $flag, $uid, $item_id);
                $stmt->execute();
            }
            
            $mysqli->query("UPDATE lab_test_orders SET status = 'النتائج جاهزة' WHERE order_id = $oid");
            $message = '✅ تم حفظ نتائج الفحوصات';
            $message_type = 'success';
        }
        
        elseif ($action === 'update_status') {
            $oid = (int)$_POST['order_id'];
            $status = sanitize_input($_POST['status']);
            $stmt = $mysqli->prepare("UPDATE lab_test_orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param('si', $status, $oid);
            $stmt->execute();
            $message = '✅ تم تحديث الحالة';
            $message_type = 'success';
        }
    }
}

// Get test categories and types for the order form
$test_categories = $mysqli->query("SELECT * FROM lab_test_categories WHERE is_active = 1 ORDER BY sort_order ASC, name_ar ASC");

// Get existing orders
$orders = null;
if ($patient_id) {
    $orders = $mysqli->query("SELECT o.*, u.full_name as ordered_by_name FROM lab_test_orders o LEFT JOIN users u ON o.ordered_by = u.user_id WHERE o.patient_id = $patient_id ORDER BY o.created_at DESC LIMIT 20");
}

// Get order details for results entry
$order_items = null;
$order_info = null;
if ($order_id) {
    $order_info = $mysqli->query("SELECT o.*, p.full_name as patient_name, p.file_number FROM lab_test_orders o JOIN patients p ON o.patient_id = p.patient_id WHERE o.order_id = $order_id")->fetch_assoc();
    $order_items = $mysqli->query("SELECT oi.*, tt.name_ar, tt.abbreviation, tt.unit, tt.normal_min, tt.normal_max FROM lab_test_order_items oi JOIN lab_test_types tt ON oi.test_type_id = tt.test_id WHERE oi.order_id = $order_id");
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🧪 الفحوصات المخبرية</h1>
    <p class="page-subtitle">طلب الفحوصات وإدخال النتائج</p>
</div>

<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div><?php endif; ?>

<div class="flex flex-wrap gap-4">
    <!-- New Order Form -->
    <div class="card" style="flex:1;min-width:350px;">
        <div class="card-header"><div class="card-title">📋 طلب فحوصات جديد</div></div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_order">
            
            <div class="field mb-3">
                <label>المريض <span class="required">*</span></label>
                <select name="patient_id" required onchange="window.location.href='order.php?patient_id='+this.value<?php echo $visit_id ? '+&visit_id='.$visit_id : ''; ?>">
                    <option value="">-- اختر المريض --</option>
                    <?php 
                    $all_patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name");
                    while ($p = $all_patients->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $p['patient_id']; ?>" <?php echo $p['patient_id'] === $patient_id ? 'selected' : ''; ?>>
                        <?php echo escape_output($p['full_name']); ?> — 📁 <?php echo escape_output($p['file_number']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <?php if ($visit_id): ?>
                <input type="hidden" name="visit_id" value="<?php echo $visit_id; ?>">
            <?php else: ?>
            <div class="field mb-3">
                <label>الزيارة (اختياري)</label>
                <select name="visit_id">
                    <option value="">بدون زيارة</option>
                    <?php 
                    if ($patient_id) {
                        $visits = $mysqli->query("SELECT visit_id, visit_number, visit_date FROM visits WHERE patient_id = $patient_id ORDER BY visit_date DESC LIMIT 10");
                        while ($v = $visits->fetch_assoc()):
                    ?>
                    <option value="<?php echo $v['visit_id']; ?>" <?php echo $v['visit_id'] === $visit_id ? 'selected' : ''; ?>>
                        زيارة #<?php echo $v['visit_number']; ?> — <?php echo $v['visit_date']; ?>
                    </option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="field mb-3">
                <label>الفحوصات المطلوبة <span class="required">*</span></label>
                <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px;">
                    <?php if ($test_categories->num_rows > 0): 
                        $test_categories->data_seek(0);
                        while ($cat = $test_categories->fetch_assoc()): 
                        $cat_tests = $mysqli->query("SELECT test_id, name_ar, abbreviation FROM lab_test_types WHERE category_id = {$cat['category_id']} AND is_active = 1 ORDER BY name_ar ASC");
                        if ($cat_tests->num_rows > 0):
                    ?>
                    <div style="margin-bottom:8px;">
                        <strong style="color:var(--teal);font-size:13px;">📂 <?php echo escape_output($cat['name_ar']); ?></strong>
                        <div style="display:flex;flex-wrap:wrap;gap:4px 12px;margin-top:4px;">
                            <?php while ($ct = $cat_tests->fetch_assoc()): ?>
                            <label class="check-item" style="font-size:12px;">
                                <input type="checkbox" name="test_ids[]" value="<?php echo $ct['test_id']; ?>">
                                <?php echo escape_output($ct['name_ar']); ?> (<?php echo escape_output($ct['abbreviation'] ?? '—'); ?>)
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; endwhile; else: ?>
                    <p style="color:#94a3b8;text-align:center;">⚠️ لا توجد فحوصات مضافة. أضف فحوصات من <a href="index.php">إدارة الفحوصات</a></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="field mb-3">
                <label>ملاحظات</label>
                <textarea name="notes" placeholder="أي ملاحظات للطبيب أو المختبر..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" <?php echo $test_categories->num_rows === 0 ? 'disabled' : ''; ?>>📋 إنشاء طلب الفحوصات</button>
        </form>
    </div>

    <!-- Recent Orders -->
    <div class="card" style="flex:1;min-width:350px;">
        <div class="card-header"><div class="card-title">📜 طلبات سابقة</div></div>
        <?php if ($orders && $orders->num_rows > 0): ?>
            <?php while ($o = $orders->fetch_assoc()): 
                $status_colors = ['معلق' => 'warning', 'تم السحب' => 'info', 'النتائج جاهزة' => 'success', 'ملغي' => 'danger'];
                $sc = $status_colors[$o['status']] ?? 'secondary';
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid var(--teal-mid);">
                <div>
                    <strong>طلب #<?php echo $o['order_id']; ?></strong>
                    <div style="font-size:12px;color:var(--text-muted);">
                        <?php echo date('Y-m-d H:i', strtotime($o['created_at'])); ?> — <?php echo escape_output($o['ordered_by_name'] ?? '—'); ?>
                    </div>
                </div>
                <div class="flex gap-2 items-center">
                    <span class="badge badge-<?php echo $sc; ?>"><?php echo $o['status']; ?></span>
                    <?php if ($o['status'] === 'معلق'): ?>
                    <form method="post" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                        <input type="hidden" name="status" value="تم السحب">
                        <button type="submit" class="btn btn-sm btn-primary">💉 تم السحب</button>
                    </form>
                    <?php endif; ?>
                    <a href="order.php?order_id=<?php echo $o['order_id']; ?><?php echo $patient_id ? '&patient_id='.$patient_id : ''; ?>" class="btn btn-sm btn-secondary">📝 نتائج</a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#94a3b8;text-align:center;padding:20px;">لا توجد طلبات سابقة</p>
        <?php endif; ?>
    </div>
</div>

<!-- Results Entry -->
<?php if ($order_info && $order_items): ?>
<div class="card mt-4">
    <div class="card-header">
        <div class="card-title">📝 إدخال نتائج — طلب #<?php echo $order_id; ?>
            <span style="font-size:13px;color:var(--text-muted);font-weight:400;">
                — <?php echo escape_output($order_info['patient_name']); ?> (📁<?php echo escape_output($order_info['file_number']); ?>)
            </span>
        </div>
        <span class="badge badge-<?php echo $status_colors[$order_info['status']] ?? 'secondary'; ?>"><?php echo $order_info['status']; ?></span>
    </div>
    
    <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_results">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        
        <table>
            <thead><tr>
                <th>الفحص</th><th>الاختصار</th><th>الوحدة</th><th>النطاق الطبيعي</th><th>النتيجة</th><th>التقييم</th>
            </tr></thead>
            <tbody>
                <?php while ($item = $order_items->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700;"><?php echo escape_output($item['name_ar']); ?></td>
                    <td><?php echo escape_output($item['abbreviation'] ?? '—'); ?></td>
                    <td><?php echo $item['unit'] ?: '—'; ?></td>
                    <td style="font-size:12px;">
                        <?php if ($item['normal_min'] !== null && $item['normal_max'] !== null): ?>
                            <?php echo $item['normal_min']; ?> – <?php echo $item['normal_max']; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><input type="text" name="results[<?php echo $item['item_id']; ?>][value]" value="<?php echo escape_output($item['result_value'] ?? ''); ?>" style="width:100px;border:1px solid var(--border);border-radius:6px;padding:4px 8px;text-align:center;font-family:'Tajawal',sans-serif;"></td>
                    <td>
                        <select name="results[<?php echo $item['item_id']; ?>][flag]" style="border:1px solid var(--border);border-radius:6px;padding:4px;font-family:'Tajawal',sans-serif;">
                            <option value="">--</option>
                            <option value="طبيعي" <?php echo ($item['result_flag'] ?? '') === 'طبيعي' ? 'selected' : ''; ?>>✅ طبيعي</option>
                            <option value="مرتفع" <?php echo ($item['result_flag'] ?? '') === 'مرتفع' ? 'selected' : ''; ?>>⬆️ مرتفع</option>
                            <option value="منخفض" <?php echo ($item['result_flag'] ?? '') === 'منخفض' ? 'selected' : ''; ?>>⬇️ منخفض</option>
                            <option value="حرج" <?php echo ($item['result_flag'] ?? '') === 'حرج' ? 'selected' : ''; ?>>🚨 حرج</option>
                        </select>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">💾 حفظ النتائج</button>
            <form method="post" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <input type="hidden" name="status" value="النتائج جاهزة">
                <button type="submit" class="btn btn-success" style="background:var(--green);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-family:'Tajawal',sans-serif;font-weight:600;cursor:pointer;">
                    ✅ تأكيد النتائج كاملة
                </button>
            </form>
        </div>
    </form>
</div>
<?php endif; ?>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div></div>
