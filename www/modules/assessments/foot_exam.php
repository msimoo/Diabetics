<?php
/**
 * Foot Clinical Exam + Wound Assessment
 */
$page_title = 'فحص القدم | Foot Assessment';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ الرجاء اختيار زيارة أولاً</div><a href='".BASE_URL."/modules/visits/add.php' class='btn btn-primary'>➕ إنشاء زيارة</a></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM foot_assessments WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

$stmt = $mysqli->prepare("SELECT * FROM foot_ulcers WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$ulcer = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_exam']) || isset($_POST['save_ulcer']))) {
    // Save foot exam
    if (isset($_POST['save_exam'])) {
        $right_sensation = sanitize_input($_POST['right_sensation'] ?? '');
        $right_pulse = sanitize_input($_POST['right_pulse'] ?? '');
        $right_deformities = isset($_POST['right_deformities']) ? implode(',', $_POST['right_deformities']) : '';
        $left_sensation = sanitize_input($_POST['left_sensation'] ?? '');
        $left_pulse = sanitize_input($_POST['left_pulse'] ?? '');
        $left_deformities = isset($_POST['left_deformities']) ? implode(',', $_POST['left_deformities']) : '';
        $abpi_right = $_POST['abpi_right'] ? (float)$_POST['abpi_right'] : null;
        $abpi_left = $_POST['abpi_left'] ? (float)$_POST['abpi_left'] : null;
        $wagner = $_POST['wagner_grade'] !== '' ? (int)$_POST['wagner_grade'] : null;
        $notes = sanitize_input($_POST['exam_notes'] ?? '');

        if ($exam) {
            $stmt = $mysqli->prepare("UPDATE foot_assessments SET right_sensation=?, right_pulse=?, right_deformities=?, left_sensation=?, left_pulse=?, left_deformities=?, abpi_right=?, abpi_left=?, wagner_grade=?, notes=? WHERE visit_id=?");
            $stmt->bind_param('ssssssddisi', $right_sensation, $right_pulse, $right_deformities, $left_sensation, $left_pulse, $left_deformities, $abpi_right, $abpi_left, $wagner, $notes, $visit_id);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO foot_assessments (visit_id, right_sensation, right_pulse, right_deformities, left_sensation, left_pulse, left_deformities, abpi_right, abpi_left, wagner_grade, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('issssssddis', $visit_id, $right_sensation, $right_pulse, $right_deformities, $left_sensation, $left_pulse, $left_deformities, $abpi_right, $abpi_left, $wagner, $notes);
        }
        if ($stmt->execute()) { $success = '✅ تم حفظ تقييم القدم'; $stmt = $mysqli->prepare("SELECT * FROM foot_assessments WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $exam = $stmt->get_result()->fetch_assoc(); }
        else { $error = '❌ خطأ: ' . $stmt->error; }
    }

    // Save ulcer
    if (isset($_POST['save_ulcer'])) {
        $cause = sanitize_input($_POST['initial_cause'] ?? '');
        $condition = sanitize_input($_POST['wound_condition'] ?? '');
        $depth = sanitize_input($_POST['wound_depth'] ?? '');
        $size = $_POST['wound_size_cm2'] ? (float)$_POST['wound_size_cm2'] : null;                $wound_x = ($_POST['wound_x_right'] ?? '') ?: ($_POST['wound_x_left'] ?? '');
                $wound_y = ($_POST['wound_y_right'] ?? '') ?: ($_POST['wound_y_left'] ?? '');
        $wound_foot = sanitize_input($_POST['wound_foot'] ?? '');

        if ($ulcer) {
            $stmt = $mysqli->prepare("UPDATE foot_ulcers SET initial_cause=?, wound_condition=?, wound_depth=?, wound_size_cm2=?, wound_location_x=?, wound_location_y=?, wound_foot=? WHERE visit_id=?");
            $stmt->bind_param('sssdddsi', $cause, $condition, $depth, $size, $wound_x, $wound_y, $wound_foot, $visit_id);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO foot_ulcers (visit_id, initial_cause, wound_condition, wound_depth, wound_size_cm2, wound_location_x, wound_location_y, wound_foot) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isssddds', $visit_id, $cause, $condition, $depth, $size, $wound_x, $wound_y, $wound_foot);
        }
        if ($stmt->execute()) { $success = '✅ تم حفظ تقييم الجرح'; $stmt = $mysqli->prepare("SELECT * FROM foot_ulcers WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $ulcer = $stmt->get_result()->fetch_assoc(); }
        else { $error = '❌ خطأ: ' . $stmt->error; }
    }
}

$deformities_list = ['قدم مخلبية', 'قدم مسطحة', 'بيس كافوس', 'أصابع مزدحمة', 'شاركوت', 'أصابع مطرقة', 'ثفنات', 'عظام مشط بارزة'];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">🦶 فحص وتقييم القدم</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?> (<?php echo $visit['visit_date']; ?>)</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<!-- Clinical Foot Exam -->
<div class="card mb-4">
<form method="post">
    <?php echo csrf_field(); ?>
    <div class="form-section-title">🔬 الفحص السريري للقدم</div>
    <div class="flex flex-wrap gap-4">
        <!-- Right Foot -->
        <div style="flex:1;min-width:200px;padding:12px;background:var(--teal-pale);border-radius:12px;">
            <h3 style="font-weight:800;color:var(--teal);margin-bottom:10px;">🦶 القدم اليمنى</h3>
            <div class="field mb-2"><label>الإحساس</label>
                <select name="right_sensation">
                    <option value="">-- اختر --</option>
                    <?php foreach (['طبيعي', 'منخفض', 'معدوم', 'متناقض'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($exam['right_sensation'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>النبض</label>
                <select name="right_pulse">
                    <option value="">-- اختر --</option>
                    <?php foreach (['طبيعي متوسط', 'ضعيف', 'معدوم'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($exam['right_pulse'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>التشوهات</label>
                <div style="display:flex;flex-direction:column;gap:4px;font-size:13px;">
                    <?php $sel_right = explode(',', $exam['right_deformities'] ?? ''); foreach ($deformities_list as $d): ?>
                    <label><input type="checkbox" name="right_deformities[]" value="<?php echo $d; ?>" <?php echo in_array($d, $sel_right) ? 'checked' : ''; ?>> <?php echo $d; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!-- Left Foot -->
        <div style="flex:1;min-width:200px;padding:12px;background:var(--teal-pale);border-radius:12px;">
            <h3 style="font-weight:800;color:var(--teal);margin-bottom:10px;">🦶 القدم اليسرى</h3>
            <div class="field mb-2"><label>الإحساس</label>
                <select name="left_sensation">
                    <option value="">-- اختر --</option>
                    <?php foreach (['طبيعي', 'منخفض', 'معدوم', 'متناقض'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($exam['left_sensation'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>النبض</label>
                <select name="left_pulse">
                    <option value="">-- اختر --</option>
                    <?php foreach (['طبيعي متوسط', 'ضعيف', 'معدوم'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($exam['left_pulse'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>التشوهات</label>
                <div style="display:flex;flex-direction:column;gap:4px;font-size:13px;">
                    <?php $sel_left = explode(',', $exam['left_deformities'] ?? ''); foreach ($deformities_list as $d): ?>
                    <label><input type="checkbox" name="left_deformities[]" value="<?php echo $d; ?>" <?php echo in_array($d, $sel_left) ? 'checked' : ''; ?>> <?php echo $d; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-4 mt-3">
        <div class="field" style="flex:1;min-width:120px;"><label>ABPI الأيمن</label><input type="number" name="abpi_right" step="0.01" min="0" max="3" value="<?php echo $exam['abpi_right'] ?? ''; ?>"></div>
        <div class="field" style="flex:1;min-width:120px;"><label>ABPI الأيسر</label><input type="number" name="abpi_left" step="0.01" min="0" max="3" value="<?php echo $exam['abpi_left'] ?? ''; ?>"></div>
        <div class="field" style="flex:1;min-width:150px;"><label>درجة Wagner (0-5)</label>
            <select name="wagner_grade">
                <option value="">-- اختر --</option>
                <?php for ($i = 0; $i <= 5; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo ($exam['wagner_grade'] ?? '') === (string)$i ? 'selected' : ''; ?>>
                    الدرجة <?php echo $i; ?> — <?php echo ['لا توجد قرحة', 'قرحة سطحية', 'قرحة عميقة', 'قرحة مع التهاب عظم', 'غرغرينا موضعية', 'غرغرينا في القدم كاملة'][$i]; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    <div class="field mt-3"><label>ملاحظات الفحص</label><textarea name="exam_notes"><?php echo escape_output($exam['notes'] ?? ''); ?></textarea></div>
    <button type="submit" name="save_exam" class="btn btn-primary mt-3">💾 حفظ الفحص السريري</button>
</form>
</div>

<!-- Wound Assessment with Canvas -->
<div class="card">
<form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="form-section-title">🩹 تقييم الجرح (اضغط على الرسم لتحديد الموقع)</div>
    
    <div class="flex flex-wrap gap-4">
        <div style="flex:1;min-width:200px;">
            <div class="field mb-2"><label>السبب الأولي</label>
                <select name="initial_cause">
                    <option value="">-- اختر --</option>
                    <?php foreach (['طعنة شوكة', 'طعنة دبوس', 'ضربة', 'تليف وخشونة', 'حذاء جديد', 'بقاقة', 'حرق', 'غير معروف'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($ulcer['initial_cause'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>حالة الجرح</label>
                <select name="wound_condition">
                    <option value="">-- اختر --</option>
                    <?php foreach (['نظيفة', 'متسخة', 'صديد', 'سوداء', 'تحوي جسم غريب'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($ulcer['wound_condition'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>عمق الجرح</label>
                <select name="wound_depth">
                    <option value="">-- اختر --</option>
                    <?php foreach (['سطحي في الجلد', 'الجلد وتحت الجلد', 'العضلات', 'العظم'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo ($ulcer['wound_depth'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field mb-2"><label>المساحة (سم²)</label><input type="number" name="wound_size_cm2" step="0.1" min="0" value="<?php echo $ulcer['wound_size_cm2'] ?? ''; ?>"></div>
            <input type="hidden" name="wound_x_right" id="wound_x_right" value="<?php echo $ulcer['wound_location_x'] ?? ''; ?>">
            <input type="hidden" name="wound_y_right" id="wound_y_right" value="<?php echo $ulcer['wound_location_y'] ?? ''; ?>">
            <input type="hidden" name="wound_x_left" id="wound_x_left" value="<?php echo $ulcer['wound_location_x'] ?? ''; ?>">
            <input type="hidden" name="wound_y_left" id="wound_y_left" value="<?php echo $ulcer['wound_location_y'] ?? ''; ?>">
            <input type="hidden" name="wound_foot" id="wound_foot" value="<?php echo $ulcer['wound_foot'] ?? ''; ?>">
        </div>

        <!-- Interactive Foot Canvas -->
        <div style="flex:1;min-width:200px;">
            <div class="flex gap-3 items-center mb-2">
                <span style="font-weight:700;color:var(--teal);">👆 اضغط على رسم القدم لتحديد مكان الجرح</span>
                <button type="button" class="btn btn-sm btn-secondary" onclick="clearMarkers()">🔄 إعادة تعيين</button>
            </div>
            <div class="flex gap-2">
                <div style="text-align:center;">
                    <p style="font-weight:700;font-size:12px;color:var(--teal);margin-bottom:4px;">القدم اليمنى</p>
                    <canvas id="footCanvasRight" style="width:160px;height:200px;border:2px solid var(--border);border-radius:8px;cursor:crosshair;"></canvas>
                </div>
                <div style="text-align:center;">
                    <p style="font-weight:700;font-size:12px;color:var(--teal);margin-bottom:4px;">القدم اليسرى</p>
                    <canvas id="footCanvasLeft" style="width:160px;height:200px;border:2px solid var(--border);border-radius:8px;cursor:crosshair;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" name="save_ulcer" class="btn btn-primary mt-3">💾 حفظ تقييم الجرح</button>
</form>
</div>

<div class="mt-4">
    <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع للزيارة</a>
    <a href="<?php echo BASE_URL; ?>/modules/visits/add.php" class="btn btn-primary">🩺 زيارة أخرى</a>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script src="<?php echo BASE_URL; ?>/assets/js/foot-canvas.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rightFoot = new FootCanvas('footCanvasRight', 'right');
    const leftFoot = new FootCanvas('footCanvasLeft', 'left');
    
    window.clearMarkers = function() {
        if (rightFoot) rightFoot.clearMarkers();
        if (leftFoot) leftFoot.clearMarkers();
        document.getElementById('wound_x_right').value = '';
        document.getElementById('wound_y_right').value = '';
        document.getElementById('wound_x_left').value = '';
        document.getElementById('wound_y_left').value = '';
        document.getElementById('wound_foot').value = '';
    };
});
</script>
</div>
