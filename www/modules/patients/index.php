<?php
/**
 * Patient List Page
 */
$page_title = 'قائمة المرضى | Patients';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Session flash messages
$success_msg = $_SESSION['success'] ?? '';
$error_msg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Search
$search = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

// Build query
$where = "WHERE p.is_active = 1";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (p.full_name LIKE ? OR p.file_number LIKE ? OR p.phone_primary LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

// Count total
$count_sql = "SELECT COUNT(*) as cnt FROM patients p $where";
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total / $per_page);

// Fetch patients
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.patient_id) as visit_count,
        (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
        FROM patients p $where 
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$per_page, $offset]));
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$patients = $stmt->get_result();
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            <div class="page-header flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">👥 قائمة المرضى</h1>
                    <p class="page-subtitle">إجمالي <?php echo $total; ?> مريض</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/modules/patients/add.php" class="btn btn-primary">➕ إضافة مريض</a>
            </div>

            <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo escape_output($success_msg); ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo escape_output($error_msg); ?></div>
            <?php endif; ?>

            <!-- Search -->
            <div class="search-box" style="position:relative;">
                <span class="search-icon">🔍</span>
                <input type="text" id="patientSearch" placeholder="بحث باسم المريض، رقم الملف، أو رقم الهاتف..." value="<?php echo escape_output($search); ?>">
            </div>

            <!-- Patients Table -->
            <div class="card">
                <div class="table-container">
                    <table id="patientsTable">
                        <thead>
                            <tr>
                                <th>رقم الملف</th>
                                <th>الاسم</th>
                                <th>العمر</th>
                                <th>الجنس</th>
                                <th>الهاتف</th>
                                <th>آخر زيارة</th>
                                <th>عدد الزيارات</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($patients->num_rows === 0): ?>
                            <tr><td colspan="8" style="color:#94a3b8;padding:40px;">لا يوجد مرضى</td></tr>
                            <?php else: ?>
                                <?php while ($p = $patients->fetch_assoc()): ?>
                                <tr class="clickable-row" data-href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>">
                                    <td><strong><?php echo escape_output($p['file_number']); ?></strong></td>
                                    <td><?php echo escape_output($p['full_name']); ?></td>
                                    <td><?php echo $p['age'] ? $p['age'] . ' سنة' : '—'; ?></td>
                                    <td><?php echo $p['gender'] ?? '—'; ?></td>
                                    <td><?php echo escape_output($p['phone_primary'] ?? '—'); ?></td>
                                    <td><?php echo $p['last_visit'] ? date('Y-m-d', strtotime($p['last_visit'])) : '—'; ?></td>
                                    <td><span class="badge badge-info"><?php echo $p['visit_count']; ?></span></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-primary">عرض</a>
                                        <a href="<?php echo BASE_URL; ?>/modules/patients/edit.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-secondary">تعديل</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex justify-center gap-2 mt-4">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>" 
                           class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live filter
    const searchInput = document.getElementById('patientSearch');
    const table = document.getElementById('patientsTable');
    
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});
</script>
