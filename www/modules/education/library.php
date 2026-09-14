<?php
/**
 * Medical Library - Document Management
 */
$page_title = '📚 المكتبة الطبية | Medical Library';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Ensure documents table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    category ENUM('بروتوكولات', 'أدلة إرشادية', 'نماذج', 'مراجع علمية', 'مطويات توعوية', 'موافقات', 'أخرى') NOT NULL DEFAULT 'أخرى',
    patient_id INT,
    uploaded_by INT NOT NULL,
    is_public TINYINT(1) DEFAULT 1,
    downloads_count INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure upload directory exists
$lib_path = __DIR__ . '/../../uploads/library';
if (!is_dir($lib_path)) {
    mkdir($lib_path, 0755, true);
    // Create an index.html to prevent directory listing
    file_put_contents($lib_path . '/index.html', '');
}

$message = '';
$message_type = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $category = sanitize_input($_POST['category'] ?? 'أخرى');
        $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
        $is_public = isset($_POST['is_public']) ? 1 : 0;

        if (empty($title) || !isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $message = '❌ يرجى إدخال العنوان واختيار ملف';
            $message_type = 'danger';
        } else {
            $file = $_FILES['document'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'txt', 'csv'];
            
            if (!in_array($ext, $allowed)) {
                $message = '❌ نوع الملف غير مسموح: ' . $ext;
                $message_type = 'danger';
            } elseif ($file['size'] > 20 * 1024 * 1024) {
                $message = '❌ حجم الملف كبير جداً (الحد الأقصى 20MB)';
                $message_type = 'danger';
            } else {
                $new_name = uniqid('doc_') . '.' . $ext;
                $dest = $lib_path . '/' . $new_name;
                
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $stmt = $mysqli->prepare("INSERT INTO documents (title, description, file_path, file_type, file_size, category, patient_id, uploaded_by, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $file_type = $ext;
                    $file_size = $file['size'];
                    $stmt->bind_param('sssisiiii', $title, $description, $new_name, $file_type, $file_size, $category, $patient_id, $_SESSION['user_id'], $is_public);
                    $stmt->execute();
                    $message = '✅ تم رفع الملف بنجاح';
                    $message_type = 'success';
                } else {
                    $message = '❌ فشل رفع الملف';
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Handle download count
if (isset($_GET['download']) && (int)$_GET['download'] > 0) {
    $doc_id = (int)$_GET['download'];
    $doc = $mysqli->query("SELECT * FROM documents WHERE document_id = $doc_id")->fetch_assoc();
    if ($doc) {
        $file_path = $lib_path . '/' . $doc['file_path'];
        if (file_exists($file_path)) {
            $mysqli->query("UPDATE documents SET downloads_count = downloads_count + 1 WHERE document_id = $doc_id");
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $doc['title'] . '.' . $doc['file_type'] . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $doc_id = (int)($_POST['document_id'] ?? 0);
        $doc = $mysqli->query("SELECT * FROM documents WHERE document_id = $doc_id")->fetch_assoc();
        if ($doc) {
            $file_path = $lib_path . '/' . $doc['file_path'];
            if (file_exists($file_path)) unlink($file_path);
            $stmt = $mysqli->prepare("DELETE FROM documents WHERE document_id = ?");
            $stmt->bind_param('i', $doc_id);
            $stmt->execute();
            $message = '✅ تم حذف الملف';
            $message_type = 'success';
        }
    }
}

// Filters
$category_filter = $_GET['category'] ?? '';
$search_query = $_GET['q'] ?? '';
$patient_filter = (int)($_GET['patient_id'] ?? 0);

$where = ['1=1'];
$params = [];
$types = '';

if ($category_filter) {
    $where[] = 'd.category = ?';
    $params[] = $category_filter;
    $types .= 's';
}
if ($search_query) {
    $where[] = '(d.title LIKE ? OR d.description LIKE ?)';
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= 'ss';
}
if ($patient_filter > 0) {
    $where[] = 'd.patient_id = ?';
    $params[] = $patient_filter;
    $types .= 'i';
}

$where_clause = implode(' AND ', $where);

// Fetch documents
$sql = "SELECT d.*, u.full_name as uploader_name, p.full_name as patient_name 
        FROM documents d 
        LEFT JOIN users u ON d.uploaded_by = u.user_id 
        LEFT JOIN patients p ON d.patient_id = p.patient_id 
        WHERE $where_clause 
        ORDER BY d.created_at DESC";

if (!empty($params)) {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $documents = $stmt->get_result();
} else {
    $documents = $mysqli->query($sql);
}

// Stats
$total_docs = $mysqli->query("SELECT COUNT(*) as cnt FROM documents")->fetch_assoc()['cnt'];
$total_downloads = $mysqli->query("SELECT SUM(downloads_count) as cnt FROM documents")->fetch_assoc()['cnt'] ?: 0;
$categories = $mysqli->query("SELECT category, COUNT(*) as cnt FROM documents GROUP BY category ORDER BY cnt DESC");

// Fetch patients for linking
$patients = $mysqli->query("SELECT patient_id, file_number, full_name FROM patients WHERE is_active = 1 ORDER BY full_name ASC LIMIT 100");
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="page-header flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">📚 المكتبة الطبية</h1>
                    <p class="page-subtitle">إدارة المستندات والأدلة والبروتوكولات الطبية</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('uploadModal')">📤 رفع ملف</button>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--teal);font-size:20px;"><?php echo $total_docs; ?></div>
                    <div class="stat-label">إجمالي الملفات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--blue);font-size:20px;"><?php echo $total_downloads; ?></div>
                    <div class="stat-label">إجمالي التحميلات</div>
                </div>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--gold);font-size:20px;"><?php echo $cat['cnt']; ?></div>
                    <div class="stat-label"><?php echo $cat['category']; ?></div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <form method="get" class="flex flex-wrap gap-3 items-center">
                    <div class="field" style="flex:1;min-width:200px;">
                        <label>بحث</label>
                        <input type="text" name="q" value="<?php echo escape_output($search_query); ?>" placeholder="بحث في العناوين...">
                    </div>
                    <div class="field" style="flex:1;min-width:150px;">
                        <label>التصنيف</label>
                        <select name="category">
                            <option value="">الكل</option>
                            <option value="بروتوكولات" <?php echo $category_filter === 'بروتوكولات' ? 'selected' : ''; ?>>بروتوكولات</option>
                            <option value="أدلة إرشادية" <?php echo $category_filter === 'أدلة إرشادية' ? 'selected' : ''; ?>>أدلة إرشادية</option>
                            <option value="نماذج" <?php echo $category_filter === 'نماذج' ? 'selected' : ''; ?>>نماذج</option>
                            <option value="مراجع علمية" <?php echo $category_filter === 'مراجع علمية' ? 'selected' : ''; ?>>مراجع علمية</option>
                            <option value="مطويات توعوية" <?php echo $category_filter === 'مطويات توعوية' ? 'selected' : ''; ?>>مطويات توعوية</option>
                            <option value="موافقات" <?php echo $category_filter === 'موافقات' ? 'selected' : ''; ?>>موافقات</option>
                            <option value="أخرى" <?php echo $category_filter === 'أخرى' ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary mt-4">🔍 بحث</button>
                    <a href="?<?php echo $patient_filter ? "patient_id=$patient_filter" : ''; ?>" class="btn btn-secondary mt-4">🔄 إعادة تعيين</a>
                </form>
            </div>

            <!-- Document Grid -->
            <?php if ($documents->num_rows === 0): ?>
                <div class="card text-center" style="padding:40px;">
                    <p style="font-size:40px;margin-bottom:12px;">📂</p>
                    <p class="text-muted">لا توجد مستندات بعد</p>
                    <button class="btn btn-primary mt-3" onclick="openModal('uploadModal')">📤 رفع أول ملف</button>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <?php while ($doc = $documents->fetch_assoc()): 
                        $file_icon = [
                            'pdf' => '📕', 'doc' => '📄', 'docx' => '📄', 
                            'xls' => '📊', 'xlsx' => '📊', 
                            'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                            'txt' => '📝', 'csv' => '📋'
                        ][$doc['file_type']] ?? '📁';
                    ?>
                    <div class="card" style="position:relative;">
                        <div style="font-size:36px;margin-bottom:8px;"><?php echo $file_icon; ?></div>
                        <h3 style="font-family:'Cairo',sans-serif;font-size:15px;font-weight:700;margin-bottom:4px;color:var(--text-heading);">
                            <?php echo escape_output($doc['title']); ?>
                        </h3>
                        <?php if ($doc['description']): ?>
                            <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><?php echo escape_output($doc['description']); ?></p>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-2 mb-2" style="font-size:11px;">
                            <span class="badge badge-info"><?php echo $doc['category']; ?></span>
                            <span class="badge badge-secondary"><?php echo strtoupper($doc['file_type']); ?></span>
                            <span class="badge badge-secondary"><?php echo round($doc['file_size'] / 1024, 1); ?> KB</span>
                            <?php if ($doc['patient_name']): ?>
                                <span class="badge badge-warning">👤 <?php echo escape_output($doc['patient_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-light);margin-bottom:10px;">
                            📤 <?php echo $doc['uploader_name'] ?: '—'; ?> | 📥 <?php echo $doc['downloads_count']; ?> | <?php echo date('Y-m-d', strtotime($doc['created_at'])); ?>
                        </div>
                        <div class="flex gap-2">
                            <a href="?download=<?php echo $doc['document_id']; ?>" class="btn btn-sm btn-primary">📥 تحميل</a>
                            <?php if ($_SESSION['user_id'] === $doc['uploaded_by'] || has_role(['super_admin', 'admin'])): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف هذا الملف؟')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="document_id" value="<?php echo $doc['document_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <h3 style="font-family:'Cairo',sans-serif;color:var(--teal);">📤 رفع ملف جديد</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="upload">

            <div class="form-grid">
                <div class="field col-span-2">
                    <label>عنوان الملف <span class="required">*</span></label>
                    <input type="text" name="title" required placeholder="أدخل عنوان الملف">
                </div>
                <div class="field col-span-2">
                    <label>وصف</label>
                    <textarea name="description" rows="2" placeholder="وصف مختصر للملف..."></textarea>
                </div>
                <div class="field">
                    <label>التصنيف <span class="required">*</span></label>
                    <select name="category" required>
                        <option value="بروتوكولات">بروتوكولات</option>
                        <option value="أدلة إرشادية">أدلة إرشادية</option>
                        <option value="نماذج">نماذج</option>
                        <option value="مراجع علمية">مراجع علمية</option>
                        <option value="مطويات توعوية">مطويات توعوية</option>
                        <option value="موافقات">موافقات</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                <div class="field">
                    <label>ربط بمريض (اختياري)</label>
                    <select name="patient_id">
                        <option value="">— بدون —</option>
                        <?php while ($p = $patients->fetch_assoc()): ?>
                            <option value="<?php echo $p['patient_id']; ?>">[<?php echo $p['file_number']; ?>] <?php echo $p['full_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field col-span-2">
                    <label>الملف <span class="required">*</span></label>
                    <input type="file" name="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">PDF, Word, Excel, PowerPoint, Images, Text - حد أقصى 20MB</div>
                </div>
                <div class="field col-span-2">
                    <label class="check-item">
                        <input type="checkbox" name="is_public" checked> متاح للجميع
                    </label>
                </div>
            </div>

            <div class="flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">📤 رفع</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('uploadModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal('uploadModal');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
