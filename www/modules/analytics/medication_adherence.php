<?php
/**
 * Medication Adherence Tracking
 * Tracks patient medication adherence rates, identifies non-adherent patients,
 * and provides visual analytics on treatment compliance
 */
$page_title = 'الالتزام بالعلاج | Medication Adherence';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Create adherence tracking table
$mysqli->query("CREATE TABLE IF NOT EXISTS medication_adherence (
    adherence_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_id INT,
    assessed_by INT,
    adherence_level ENUM('ملتزم', 'ملتزم جزئياً', 'غير ملتزم', 'لم يتم التقييم') NOT NULL DEFAULT 'لم يتم التقييم',
    missed_doses ENUM('لا', 'أحياناً', 'غالباً', 'دائماً') NOT NULL DEFAULT 'لا',
    reason_for_non_adherence TEXT,
    notes TEXT,
    assessment_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE SET NULL,
    FOREIGN KEY (assessed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Handle save
$saved = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_adherence'])) {
    $patient_id = (int)$_POST['patient_id'];
    $visit_id = !empty($_POST['visit_id']) ? (int)$_POST['visit_id'] : null;
    $adherence = sanitize_input($_POST['adherence_level']);
    $missed = sanitize_input($_POST['missed_doses']);
    $reason = sanitize_input($_POST['reason'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $date = $_POST['assessment_date'] ?? date('Y-m-d');
    $user_id = (int)$_SESSION['user_id'];

    // Check if assessment exists for this patient+visit
    if ($visit_id) {
        $check = $mysqli->prepare("SELECT adherence_id FROM medication_adherence WHERE patient_id = ? AND visit_id = ?");
        $check->bind_param('ii', $patient_id, $visit_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        if ($existing) {
            $stmt = $mysqli->prepare("UPDATE medication_adherence SET adherence_level=?, missed_doses=?, reason_for_non_adherence=?, notes=?, assessment_date=?, assessed_by=? WHERE adherence_id=?");
            $stmt->bind_param('sssssii', $adherence, $missed, $reason, $notes, $date, $user_id, $existing['adherence_id']);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO medication_adherence (patient_id, visit_id, assessed_by, adherence_level, missed_doses, reason_for_non_adherence, notes, assessment_date) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iiisssss', $patient_id, $visit_id, $user_id, $adherence, $missed, $reason, $notes, $date);
        }
    } else {
        $check = $mysqli->prepare("SELECT adherence_id FROM medication_adherence WHERE patient_id = ? AND assessment_date = ?");
        $check->bind_param('is', $patient_id, $date);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        if ($existing) {
            $stmt = $mysqli->prepare("UPDATE medication_adherence SET adherence_level=?, missed_doses=?, reason_for_non_adherence=?, notes=?, assessed_by=? WHERE adherence_id=?");
            $stmt->bind_param('ssssii', $adherence, $missed, $reason, $notes, $user_id, $existing['adherence_id']);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO medication_adherence (patient_id, assessed_by, adherence_level, missed_doses, reason_for_non_adherence, notes, assessment_date) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('iisssss', $patient_id, $user_id, $adherence, $missed, $reason, $notes, $date);
        }
    }
    if ($stmt->execute()) {
        $saved = '✅ تم حفظ تقييم الالتزام';
    } else {
        $saved = '❌ خطأ: ' . $stmt->error;
    }
}

// === ADHERENCE ANALYTICS ===

// 1. Overall adherence rates
$adherence_stats = [];
$result = $mysqli->query("SELECT 
    adherence_level, COUNT(*) as cnt 
    FROM medication_adherence 
    WHERE adherence_id IN (SELECT MAX(adherence_id) FROM medication_adherence GROUP BY patient_id)
    GROUP BY adherence_level");
while ($row = $result->fetch_assoc()) { $adherence_stats[] = $row; }
$total_assessed = array_sum(array_column($adherence_stats, 'cnt'));

// 2. Missed doses distribution
$missed_stats = [];
$result = $mysqli->query("SELECT missed_doses, COUNT(*) as cnt 
    FROM medication_adherence 
    WHERE adherence_id IN (SELECT MAX(adherence_id) FROM medication_adherence GROUP BY patient_id)
    GROUP BY missed_doses");
while ($row = $result->fetch_assoc()) { $missed_stats[] = $row; }

// 3. Patients on treatment (from treatments table linked through visits)
$patients_on_treatment = $mysqli->query("SELECT DISTINCT p.patient_id, p.full_name, p.file_number, p.phone_primary
    FROM patients p 
    JOIN visits v ON p.patient_id = v.patient_id 
    JOIN treatments t ON v.visit_id = t.visit_id 
    WHERE p.is_active = 1 
    ORDER BY p.full_name");

// 4. Inferred non-adherence from outcomes + treatments
$inferred_non_adherent = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, p.phone_primary,
    t.treatment_type, t.oral_meds_details, t.insulin_details,
    o.improvement_percentage,
    v.visit_date,
    DATEDIFF(CURDATE(), v.visit_date) as days_since_visit
    FROM patients p 
    JOIN visits v ON p.patient_id = v.patient_id AND v.visit_id = (SELECT MAX(v2.visit_id) FROM visits v2 WHERE v2.patient_id = p.patient_id)
    JOIN treatments t ON v.visit_id = t.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.is_active = 1 
    AND (o.improvement_percentage IS NULL OR o.improvement_percentage < 50)
    AND (t.treatment_type IS NOT NULL AND t.treatment_type != '')
    ORDER BY o.improvement_percentage IS NULL, o.improvement_percentage ASC, days_since_visit DESC
    LIMIT 20");

// 5. Latest adherence assessments
$latest_assessments = $mysqli->query("SELECT ma.*, p.full_name, p.file_number, u.full_name as doctor_name
    FROM medication_adherence ma
    JOIN patients p ON ma.patient_id = p.patient_id
    LEFT JOIN users u ON ma.assessed_by = u.user_id
    WHERE ma.adherence_id IN (SELECT MAX(adherence_id) FROM medication_adherence GROUP BY patient_id)
    ORDER BY ma.assessment_date DESC LIMIT 30");

// 6. Patients needing assessment (have treatments but no adherence record)
$need_assessment = $mysqli->query("SELECT DISTINCT p.patient_id, p.full_name, p.file_number, 
    (SELECT MAX(v.visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    JOIN treatments t ON v.visit_id = t.visit_id
    WHERE p.is_active = 1 
    AND p.patient_id NOT IN (SELECT DISTINCT patient_id FROM medication_adherence)
    ORDER BY p.full_name");
?>
<style>
    .adherence-hero {
        background: linear-gradient(135deg, #7b1fa2, #4a148c); color: white;
        padding: 2rem; border-radius: 16px; margin-bottom: 2rem; text-align: center;
        position: relative; overflow: hidden;
    }
    .adherence-hero::before { content: '💊'; position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; }

    .adherence-stat {
        background: white; border-radius: 12px; padding: 1.2rem; text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s;
    }
    .adherence-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
    .adherence-stat .as-value { font-size: 2rem; font-weight: 800; }
    .adherence-stat .as-label { font-size: 0.85rem; color: #666; margin-top: 0.2rem; }
    .adherence-stat .as-bar { height: 4px; border-radius: 2px; margin-top: 0.5rem; }

    .adherence-badge {
        display: inline-block; padding: 3px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;
    }
    .badge-adherent { background: #e8f5e9; color: #2e7d32; }
    .badge-partial { background: #fff3e0; color: #e65100; }
    .badge-nonadherent { background: #ffebee; color: #c62828; }
    .badge-unassessed { background: #f3e5f5; color: #7b1fa2; }

    .assessment-form { background: #f3e5f5; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #ce93d8; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="adherence-hero fade-in">
    <h1>💊 متابعة الالتزام بالعلاج</h1>
    <p>Medication Adherence Tracking — تقييم مدى التزام المرضى بخطة العلاج</p>
    <div style="margin-top:0.8rem;display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;">
        <span style="background:rgba(255,255,255,0.15);padding:0.3rem 1rem;border-radius:20px;font-size:0.9rem;">
            تم تقييم: <strong><?php echo $total_assessed; ?></strong> مريض
        </span>
        <span style="background:rgba(255,255,255,0.15);padding:0.3rem 1rem;border-radius:20px;font-size:0.9rem;">
            يحتاج تقييم: <strong style="color:#ef9a9a;"><?php echo $need_assessment->num_rows; ?></strong> مريض
        </span>
    </div>
</div>

<?php if ($saved): ?>
<div class="alert alert-success"><?php echo $saved; ?></div>
<?php endif; ?>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Adherence Pie Chart -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">📊 توزيع الالتزام</div></div>
        <div style="height:220px;"><canvas id="adherenceChart" width="400" height="220"></canvas></div>
        <?php if ($total_assessed === 0): ?><p style="color:#94a3b8;text-align:center;">لم يتم تقييم أي مريض بعد</p><?php endif; ?>
    </div>
    <!-- Missed Doses Chart -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">📊 تكرار الجرعات الفائتة</div></div>
        <div style="height:220px;"><canvas id="missedChart" width="400" height="220"></canvas></div>
        <?php if (empty($missed_stats)): ?><p style="color:#94a3b8;text-align:center;">لا توجد بيانات</p><?php endif; ?>
    </div>
    <!-- Quick Stats -->
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">📈 مؤشرات سريعة</div></div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php 
            $adherent = 0; $partial = 0; $non_adherent = 0;
            foreach ($adherence_stats as $s) {
                if ($s['adherence_level'] === 'ملتزم') $adherent = $s['cnt'];
                elseif ($s['adherence_level'] === 'ملتزم جزئياً') $partial = $s['cnt'];
                elseif ($s['adherence_level'] === 'غير ملتزم') $non_adherent = $s['cnt'];
            }
            $adherence_rate = $total_assessed > 0 ? round(($adherent / $total_assessed) * 100) : 0;
            ?>
            <div style="text-align:center;padding:0.8rem;background:#e8f5e9;border-radius:10px;">
                <div style="font-size:1.8rem;font-weight:800;color:#2e7d32;"><?php echo $adherence_rate; ?>%</div>
                <div style="font-size:0.8rem;color:#555;">نسبة الالتزام الكلي</div>
            </div>
            <div style="text-align:center;padding:0.8rem;background:#ffebee;border-radius:10px;">
                <div style="font-size:1.8rem;font-weight:800;color:#c62828;"><?php echo $non_adherent; ?></div>
                <div style="font-size:0.8rem;color:#555;">مرضى غير ملتزمين</div>
            </div>
            <div style="text-align:center;padding:0.8rem;background:#e3f2fd;border-radius:10px;">
                <div style="font-size:1.8rem;font-weight:800;color:#1565c0;"><?php echo $need_assessment->num_rows; ?></div>
                <div style="font-size:0.8rem;color:#555;">يحتاجون تقييماً</div>
            </div>
        </div>
    </div>
</div>

<!-- Adherence Assessment Form -->
<div class="assessment-form fade-in">
    <h3 style="color:#7b1fa2;margin-bottom:1rem;">📝 تسجيل تقييم الالتزام</h3>
    <form method="post" class="flex flex-wrap gap-3 items-end" id="adherenceForm">
        <?php echo csrf_field(); ?>
        <div class="field" style="flex:2;min-width:200px;">
            <label>المريض</label>
            <select name="patient_id" required>
                <option value="">-- اختر المريض --</option>
                <?php 
                $all_patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name");
                while ($p = $all_patients->fetch_assoc()): ?>
                <option value="<?php echo $p['patient_id']; ?>"><?php echo escape_output($p['full_name']); ?> — 📁 <?php echo escape_output($p['file_number']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>زيارة</label>
            <select name="visit_id">
                <option value="">-- آخر زيارة --</option>
            </select>
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>درجة الالتزام</label>
            <select name="adherence_level" required>
                <option value="ملتزم">✅ ملتزم</option>
                <option value="ملتزم جزئياً">🟡 ملتزم جزئياً</option>
                <option value="غير ملتزم">❌ غير ملتزم</option>
            </select>
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>جرعات فائتة</label>
            <select name="missed_doses" required>
                <option value="لا">لا</option>
                <option value="أحياناً">أحياناً</option>
                <option value="غالباً">غالباً</option>
                <option value="دائماً">دائماً</option>
            </select>
        </div>
        <div class="field" style="flex:1;min-width:120px;">
            <label>التاريخ</label>
            <input type="date" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <button type="submit" name="save_adherence" class="btn btn-primary" style="flex-shrink:0;">💾 حفظ</button>
    </form>
    <div class="flex flex-wrap gap-3 mt-2">
        <div class="field" style="flex:1;min-width:250px;">
            <label>سبب عدم الالتزام</label>
            <input type="text" name="reason" form="adherenceForm" placeholder="السبب (اختياري)...">
        </div>
        <div class="field" style="flex:1;min-width:250px;">
            <label>ملاحظات</label>
            <input type="text" name="notes" form="adherenceForm" placeholder="ملاحظات إضافية...">
        </div>
    </div>
</div>

<!-- Patients Needing Assessment -->
<?php if ($need_assessment->num_rows > 0): ?>
<div class="card mb-4" style="border-right:3px solid #7b1fa2;">
    <div class="card-header">
        <div class="card-title" style="color:#7b1fa2;">👤 مرضى يحتاجون تقييم التزام — <?php echo $need_assessment->num_rows; ?></div>
        <span style="font-size:0.85rem;color:#666;">هؤلاء المرضى لديهم علاج موصوف ولكن لم يتم تقييم التزامهم بعد</span>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>المريض</th><th>رقم الملف</th><th>آخر زيارة</th><th></th></tr></thead>
            <tbody>
                <?php while ($p = $need_assessment->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo escape_output($p['full_name']); ?></strong></td>
                    <td>📁 <?php echo escape_output($p['file_number']); ?></td>
                    <td><?php echo $p['last_visit'] ?? '—'; ?></td>
                    <td>                <a href="<?php echo BASE_URL; ?>/modules/analytics/medication_adherence.php?patient_id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-primary">تقييم</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Inferred Non-Adherence -->
<div class="card mb-4" style="border-right:3px solid #dc2626;">
    <div class="card-header">
        <div class="card-title" style="color:#dc2626;">⚠️ حالات يشتبه بعدم الالتزام</div>
        <span style="font-size:0.85rem;color:#666;">مرضى لديهم علاج موصوف ولكن نتائج غير مرضية (تحسن < 50%)</span>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>المريض</th><th>نوع العلاج</th><th>آخر زيارة</th><th>نسبة التحسن</th><th>التوصية</th><th></th></tr></thead>
            <tbody>
                <?php if ($inferred_non_adherent->num_rows === 0): ?>
                <tr><td colspan="6" style="color:#94a3b8;text-align:center;padding:1.5rem;">لا توجد حالات مشتبه بها</td></tr>
                <?php else: while ($p = $inferred_non_adherent->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo escape_output($p['full_name']); ?></strong><br><span style="font-size:11px;color:#94a3b8;">📁 <?php echo escape_output($p['file_number']); ?></span></td>
                    <td style="font-size:12px;">
                        <?php 
                        $types = explode(',', $p['treatment_type'] ?? '');
                        foreach ($types as $t) echo "<span style='background:#f3e5f5;padding:1px 6px;border-radius:4px;margin:1px;display:inline-block;font-size:11px;'>$t</span> ";
                        ?>
                    </td>
                    <td style="font-size:12px;"><?php echo $p['visit_date'] ?? '—'; ?></td>
                    <td>
                        <?php $imp = $p['improvement_percentage']; ?>
                        <span class="adherence-badge <?php echo ($imp && $imp >= 75) ? 'badge-adherent' : (($imp && $imp >= 25) ? 'badge-partial' : 'badge-nonadherent'); ?>">
                            <?php echo $imp ? $imp . '%' : '—'; ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:#dc2626;">🔍 متابعة الالتزام بالعلاج الموصوف</td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-secondary">عرض</a>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Latest Assessments -->
<div class="card">
    <div class="card-header"><div class="card-title">📋 آخر تقييمات الالتزام</div></div>
    <div class="table-container">
        <table>
            <thead><tr><th>المريض</th><th>الالتزام</th><th>جرعات فائتة</th><th>السبب</th><th>تاريخ التقييم</th><th>بواسطة</th></tr></thead>
            <tbody>
                <?php if ($latest_assessments->num_rows === 0): ?>
                <tr><td colspan="6" style="color:#94a3b8;text-align:center;padding:1.5rem;">لا توجد تقييمات بعد. استخدم النموذج أعلاه لتسجيل أول تقييم.</td></tr>
                <?php else: while ($a = $latest_assessments->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo escape_output($a['full_name']); ?></strong><br><span style="font-size:11px;color:#94a3b8;">📁 <?php echo escape_output($a['file_number']); ?></span></td>
                    <td>
                        <span class="adherence-badge <?php 
                            echo $a['adherence_level'] === 'ملتزم' ? 'badge-adherent' : ($a['adherence_level'] === 'ملتزم جزئياً' ? 'badge-partial' : 'badge-nonadherent'); 
                        ?>">
                            <?php echo $a['adherence_level']; ?>
                        </span>
                    </td>
                    <td><?php echo $a['missed_doses']; ?></td>
                    <td style="font-size:12px;max-width:200px;"><?php echo escape_output($a['reason_for_non_adherence'] ?? '—'); ?></td>
                    <td style="font-size:12px;"><?php echo $a['assessment_date']; ?></td>
                    <td style="font-size:12px;"><?php echo escape_output($a['doctor_name'] ?? '—'); ?></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adherence distribution chart
    const adhStats = <?php echo json_encode($adherence_stats, JSON_UNESCAPED_UNICODE); ?>;
    if (adhStats.length > 0) {
        const ac = new ClinicChart('adherenceChart');
        ac.drawPieChart(
            adhStats.map(d => ({ label: d.adherence_level, value: parseInt(d.cnt) })),
            { donut: true, colors: ['#2e7d32', '#f59e0b', '#c62828', '#94a3b8'] }
        );
    }

    // Missed doses chart
    const missStats = <?php echo json_encode($missed_stats, JSON_UNESCAPED_UNICODE); ?>;
    if (missStats.length > 0) {
        const mc = new ClinicChart('missedChart');
        mc.drawBarChart(
            missStats.map(d => d.missed_doses),
            missStats.map(d => parseInt(d.cnt)),
            { colors: ['#2e7d32', '#f59e0b', '#e65100', '#c62828'] }
        );
    }
});

// Load visits for selected patient
document.querySelector('[name="patient_id"]')?.addEventListener('change', function() {
    const pid = this.value;
    const visitSelect = document.querySelector('[name="visit_id"]');
    visitSelect.innerHTML = '<option value="">-- جاري التحميل --</option>';
    if (pid) {            fetch('<?php echo BASE_URL; ?>/api/visits.php?action=list_by_patient&patient_id=' + pid)
            .then(r => r.json())
            .then(data => {
                visitSelect.innerHTML = '<option value="">-- اختر الزيارة (اختياري) --</option>';
                if (Array.isArray(data)) {
                    data.forEach(v => {
                        visitSelect.innerHTML += `<option value="${v.visit_id}">📅 ${v.visit_date} — زيارة #${v.visit_number}</option>`;
                    });
                }
            })
            .catch(() => {
                visitSelect.innerHTML = '<option value="">-- تعذر التحميل --</option>';
            });
    } else {
        visitSelect.innerHTML = '<option value="">-- آخر زيارة --</option>';
    }
});
</script>
</div></div>