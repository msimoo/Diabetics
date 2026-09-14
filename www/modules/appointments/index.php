<?php
/**
 * Appointment & Calendar System
 */
$page_title = '📅 المواعيد | Appointments';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Ensure appointments table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    user_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    appointment_type ENUM('متابعة', 'فحص قدم', 'استشارة', 'إجراء', 'تحليل', 'أخرى') NOT NULL DEFAULT 'متابعة',
    status ENUM('مؤكد', 'قيد الانتظار', 'ملغي', 'مكتمل', 'لم يحضر') NOT NULL DEFAULT 'قيد الانتظار',
    notes TEXT,
    reminder_sent TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            $appointment_date = $mysqli->real_escape_string($_POST['appointment_date'] ?? '');
            $appointment_time = $mysqli->real_escape_string($_POST['appointment_time'] ?? '');
            $appointment_type = $mysqli->real_escape_string($_POST['appointment_type'] ?? 'متابعة');
            $notes = $mysqli->real_escape_string($_POST['notes'] ?? '');
            $status = $mysqli->real_escape_string($_POST['status'] ?? 'قيد الانتظار');

            // Check for time conflicts
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            $conflict_sql = "SELECT COUNT(*) as cnt FROM appointments 
                             WHERE appointment_date = '$appointment_date' 
                             AND appointment_time = '$appointment_time' 
                             AND status NOT IN ('ملغي', 'مكتمل')
                             AND appointment_id != $appointment_id";
            $conflict = $mysqli->query($conflict_sql)->fetch_assoc()['cnt'];

            if ($conflict > 0) {
                $message = '⚠️ يوجد تعارض في الموعد - هذا الوقت محجوز بالفعل';
                $message_type = 'warning';
            } elseif (empty($patient_id) || empty($appointment_date) || empty($appointment_time)) {
                $message = '❌ يرجى إكمال الحقول المطلوبة';
                $message_type = 'danger';
            } else {
                if ($action === 'add') {
                    $stmt = $mysqli->prepare("INSERT INTO appointments (patient_id, user_id, appointment_date, appointment_time, appointment_type, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iisssss', $patient_id, $_SESSION['user_id'], $appointment_date, $appointment_time, $appointment_type, $status, $notes);
                    $stmt->execute();
                    $message = '✅ تم إضافة الموعد بنجاح';
                    $message_type = 'success';
                } else {
                    $stmt = $mysqli->prepare("UPDATE appointments SET patient_id=?, appointment_date=?, appointment_time=?, appointment_type=?, status=?, notes=? WHERE appointment_id=?");
                    $stmt->bind_param('isssssi', $patient_id, $appointment_date, $appointment_time, $appointment_type, $status, $notes, $appointment_id);
                    $stmt->execute();
                    $message = '✅ تم تحديث الموعد بنجاح';
                    $message_type = 'success';
                }
            }
        } elseif ($action === 'delete') {
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            $stmt = $mysqli->prepare("DELETE FROM appointments WHERE appointment_id = ?");
            $stmt->bind_param('i', $appointment_id);
            $stmt->execute();
            $message = '✅ تم إلغاء الموعد';
            $message_type = 'success';
        } elseif ($action === 'update_status') {
            $appointment_id = (int)($_POST['appointment_id'] ?? 0);
            $status = $mysqli->real_escape_string($_POST['status'] ?? 'قيد الانتظار');
            $stmt = $mysqli->prepare("UPDATE appointments SET status=? WHERE appointment_id=?");
            $stmt->bind_param('si', $status, $appointment_id);
            $stmt->execute();
            $message = '✅ تم تحديث الحالة';
            $message_type = 'success';
        }
    }
}

// Get current month/year from URL or use current
$view_mode = $_GET['view'] ?? 'month';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_month = (int)($_GET['month'] ?? date('m'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

// Fetch patients for dropdown
$patients = $mysqli->query("SELECT patient_id, file_number, full_name FROM patients WHERE is_active = 1 ORDER BY full_name ASC");

// Fetch appointments for the current month
$first_day = "$selected_year-$selected_month-01";
$last_day = date('Y-m-t', strtotime($first_day));

$appointments = $mysqli->query("
    SELECT a.*, p.full_name, p.file_number, p.phone_primary, u.full_name as doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.appointment_date BETWEEN '$first_day' AND '$last_day'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

// Fetch appointments for selected day
$day_appointments = $mysqli->query("
    SELECT a.*, p.full_name, p.file_number, p.phone_primary, u.full_name as doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.appointment_date = '$selected_date'
    ORDER BY a.appointment_time ASC
");

// Fetch today's appointments for the summary
$today = date('Y-m-d');
$today_appointments = $mysqli->query("
    SELECT a.*, p.full_name, p.file_number
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_date = '$today' AND a.status NOT IN ('ملغي')
    ORDER BY a.appointment_time ASC
");

// Stats
$total_month = $appointments->num_rows;
$status_counts = [];
foreach (['مؤكد', 'قيد الانتظار', 'مكتمل', 'ملغي', 'لم يحضر'] as $s) {
    $r = $mysqli->query("SELECT COUNT(*) as cnt FROM appointments WHERE appointment_date BETWEEN '$first_day' AND '$last_day' AND status = '$s'");
    $status_counts[$s] = $r->fetch_assoc()['cnt'];
}

$month_name = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];
$day_names = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

// Build calendar grid
$days_in_month = date('t', strtotime($first_day));
$start_dow = date('w', strtotime($first_day)); // 0=Sun
$prev_month = $selected_month == 1 ? 12 : $selected_month - 1;
$prev_year = $selected_month == 1 ? $selected_year - 1 : $selected_year;
$next_month = $selected_month == 12 ? 1 : $selected_month + 1;
$next_year = $selected_month == 12 ? $selected_year + 1 : $selected_year;

// Build appointment map: day -> [appointments]
$appt_map = [];
while ($a = $appointments->fetch_assoc()) {
    $day = (int)date('d', strtotime($a['appointment_date']));
    $appt_map[$day][] = $a;
}
$appointments->data_seek(0);
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">📅 المواعيد</h1>
                    <p class="page-subtitle">جدولة وإدارة مواعيد المرضى</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('appointmentModal')">➕ موعد جديد</button>
            </div>

            <!-- Stats Row -->
            <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));">
                <div class="stat-card"><div class="stat-value" style="color:var(--teal);font-size:18px;"><?php echo $total_month; ?></div><div class="stat-label">إجمالي الشهر</div></div>
                <div class="stat-card"><div class="stat-value" style="color:var(--green);font-size:18px;"><?php echo $status_counts['مؤكد'] ?? 0; ?></div><div class="stat-label">مؤكد</div></div>
                <div class="stat-card"><div class="stat-value" style="color:var(--orange);font-size:18px;"><?php echo $status_counts['قيد الانتظار'] ?? 0; ?></div><div class="stat-label">قيد الانتظار</div></div>
                <div class="stat-card"><div class="stat-value" style="color:var(--blue);font-size:18px;"><?php echo $status_counts['مكتمل'] ?? 0; ?></div><div class="stat-label">مكتمل</div></div>
                <div class="stat-card"><div class="stat-value" style="color:var(--red);font-size:18px;"><?php echo $status_counts['ملغي'] ?? 0; ?></div><div class="stat-label">ملغي</div></div>
                <div class="stat-card"><div class="stat-value" style="color:var(--gray);font-size:18px;"><?php echo $status_counts['لم يحضر'] ?? 0; ?></div><div class="stat-label">لم يحضر</div></div>
            </div>

            <!-- Month Navigation + Today's Appointments -->
            <div class="flex flex-wrap gap-4" style="margin-bottom:24px;">
                
                <!-- Calendar Card -->
                <div class="card" style="flex:2;min-width:400px;">
                    <div class="card-header">
                        <div class="card-title">📅 <?php echo $month_name[$selected_month] . ' ' . $selected_year; ?></div>
                        <div class="flex gap-2">
                            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>&date=<?php echo $selected_date; ?>" class="btn btn-sm btn-secondary">◀</a>
                            <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>&date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-primary">اليوم</a>
                            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>&date=<?php echo $selected_date; ?>" class="btn btn-sm btn-secondary">▶</a>
                        </div>
                    </div>
                    <div class="table-container">
                        <table style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th style="width:14.28%;">أحد</th>
                                    <th style="width:14.28%;">إثنين</th>
                                    <th style="width:14.28%;">ثلاثاء</th>
                                    <th style="width:14.28%;">أربعاء</th>
                                    <th style="width:14.28%;">خميس</th>
                                    <th style="width:14.28%;">جمعة</th>
                                    <th style="width:14.28%;">سبت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $day = 1 - $start_dow;
                                for ($row = 0; $row < 6; $row++) {
                                    echo '<tr>';
                                    for ($col = 0; $col < 7; $col++) {
                                        if ($day < 1 || $day > $days_in_month) {
                                            echo '<td style="padding:6px;opacity:0.3;"></td>';
                                        } else {
                                            $date_str = "$selected_year-$selected_month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                            $is_today = ($date_str === date('Y-m-d'));
                                            $is_selected = ($date_str === $selected_date);
                                            $day_appts = $appt_map[$day] ?? [];
                                            $count = count($day_appts);
                                            $highlight = $count > 0 ? 'background:var(--teal-pale);border-radius:4px;' : '';
                                            echo '<td style="padding:4px;vertical-align:top;cursor:pointer;' . $highlight . '" onclick="location.href=\'?month=' . $selected_month . '&year=' . $selected_year . '&date=' . $date_str . '\'">';
                                            echo '<div style="font-weight:bold;font-size:14px;' . ($is_today ? 'color:var(--teal);' : '') . ($is_selected ? 'background:var(--teal);color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;' : '') . '">' . $day . '</div>';
                                            if ($count > 0) {
                                                echo '<div style="font-size:10px;color:var(--teal);font-weight:600;">' . $count . ' مواعيد</div>';
                                            }
                                            echo '</td>';
                                        }
                                        $day++;
                                    }
                                    echo '</tr>';
                                    if ($day > $days_in_month) break;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Today's Appointments Summary -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header">
                        <div class="card-title">🕐 مواعيد اليوم</div>
                    </div>
                    <?php if ($today_appointments->num_rows === 0): ?>
                        <p class="text-muted" style="padding:12px 0;">لا توجد مواعيد اليوم</p>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <?php while ($ta = $today_appointments->fetch_assoc()): ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--bg-input);border-radius:8px;font-size:13px;">
                                    <div>
                                        <strong><?php echo escape_output($ta['full_name']); ?></strong>
                                        <div class="text-muted" style="font-size:11px;"><?php echo $ta['file_number']; ?></div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <span style="font-weight:700;color:var(--teal);"><?php echo date('H:i', strtotime($ta['appointment_time'])); ?></span>
                                        <span class="badge badge-<?php echo $ta['status'] === 'مؤكد' ? 'success' : ($ta['status'] === 'قيد الانتظار' ? 'warning' : 'info'); ?>"><?php echo $ta['status']; ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Selected Day Appointments -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 مواعيد <?php echo date('Y-m-d', strtotime($selected_date)); ?></div>
                    <button class="btn btn-sm btn-primary" onclick="openModal('appointmentModal')">➕ إضافة</button>
                </div>
                <?php if ($day_appointments->num_rows === 0): ?>
                    <p class="text-muted" style="padding:16px 0;text-align:center;">لا توجد مواعيد في هذا اليوم</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>الوقت</th>
                                    <th>رقم الملف</th>
                                    <th>اسم المريض</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                    <th>ملاحظات</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($a = $day_appointments->fetch_assoc()): 
                                    $status_class = [
                                        'مؤكد' => 'success',
                                        'قيد الانتظار' => 'warning',
                                        'ملغي' => 'danger',
                                        'مكتمل' => 'info',
                                        'لم يحضر' => 'danger'
                                    ][$a['status']] ?? 'info';
                                ?>
                                <tr>
                                    <td><strong><?php echo date('H:i', strtotime($a['appointment_time'])); ?></strong></td>
                                    <td><?php echo escape_output($a['file_number']); ?></td>
                                    <td><a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $a['patient_id']; ?>" style="font-weight:600;"><?php echo escape_output($a['full_name']); ?></a></td>
                                    <td><span class="badge badge-info"><?php echo $a['appointment_type']; ?></span></td>
                                    <td>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('تحديث الحالة؟')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                            <select name="status" onchange="this.form.submit()" style="padding:3px 6px;border-radius:6px;border:1px solid var(--border);font-size:12px;background:var(--bg-input);color:var(--text-body);">
                                                <?php foreach (['قيد الانتظار', 'مؤكد', 'مكتمل', 'لم يحضر', 'ملغي'] as $s): ?>
                                                    <option value="<?php echo $s; ?>" <?php echo $a['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td style="font-size:12px;color:var(--text-muted);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo escape_output($a['notes'] ?: '—'); ?></td>
                                    <td>
                                        <div class="flex gap-1">
                                            <button class="btn btn-sm btn-secondary" onclick="editAppointment(<?php echo $a['appointment_id']; ?>, <?php echo $a['patient_id']; ?>, '<?php echo $a['appointment_date']; ?>', '<?php echo $a['appointment_time']; ?>', '<?php echo $a['appointment_type']; ?>', '<?php echo $a['status']; ?>', '<?php echo addslashes($a['notes'] ?? ''); ?>')">✏️</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('إلغاء هذا الموعد؟')">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <!-- Appointment Modal -->
        <div class="modal-overlay" id="appointmentModal">
<div class="modal-overlay" id="appointmentModal">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <h3 id="modalTitle" style="font-family:'Cairo',sans-serif;color:var(--teal);">➕ موعد جديد</h3>
            <button class="modal-close" onclick="closeModal('appointmentModal')">&times;</button>
        </div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="appointment_id" id="appointmentId" value="0">

            <div class="form-grid">
                <div class="field col-span-2">
                    <label>المريض <span class="required">*</span></label>
                    <select name="patient_id" id="apptPatientId" required>
                        <option value="">-- اختر المريض --</option>
                        <?php while ($p = $patients->fetch_assoc()): ?>
                            <option value="<?php echo $p['patient_id']; ?>">[<?php echo $p['file_number']; ?>] <?php echo $p['full_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field">
                    <label>التاريخ <span class="required">*</span></label>
                    <input type="date" name="appointment_date" id="apptDate" value="<?php echo $selected_date; ?>" required>
                </div>
                <div class="field">
                    <label>الوقت <span class="required">*</span></label>
                    <input type="time" name="appointment_time" id="apptTime" required>
                </div>
                <div class="field">
                    <label>نوع الموعد</label>
                    <select name="appointment_type">
                        <option value="متابعة">متابعة</option>
                        <option value="فحص قدم">فحص قدم</option>
                        <option value="استشارة">استشارة</option>
                        <option value="إجراء">إجراء</option>
                        <option value="تحليل">تحليل</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                <div class="field">
                    <label>الحالة</label>
                    <select name="status">
                        <option value="قيد الانتظار">قيد الانتظار</option>
                        <option value="مؤكد">مؤكد</option>
                        <option value="مكتمل">مكتمل</option>
                        <option value="لم يحضر">لم يحضر</option>
                        <option value="ملغي">ملغي</option>
                    </select>
                </div>
                <div class="field col-span-2">
                    <label>ملاحظات</label>
                    <textarea name="notes" id="apptNotes" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                </div>
            </div>

            <div class="flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">💾 حفظ</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('appointmentModal')">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAppointment(id, patientId, date, time, type, status, notes) {
    document.getElementById('modalTitle').textContent = '✏️ تعديل الموعد';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('appointmentId').value = id;
    document.getElementById('apptPatientId').value = patientId;
    document.getElementById('apptDate').value = date;
    document.getElementById('apptTime').value = time;
    
    const typeSelect = document.querySelector('select[name="appointment_type"]');
    const statusSelect = document.querySelector('select[name="status"]');
    for (let opt of typeSelect.options) if (opt.value === type) opt.selected = true;
    for (let opt of statusSelect.options) if (opt.value === status) opt.selected = true;
    
    document.getElementById('apptNotes').value = notes;
    openModal('appointmentModal');
}

// Reset form on modal open
document.getElementById('appointmentModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal('appointmentModal');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
