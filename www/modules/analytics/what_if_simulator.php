<?php
/**
 * What-If Simulator — Simulate the impact of clinical changes on patient risk
 */
$page_title = '🔮 محاكي ماذا لو | What-If Simulator';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get all active patients for select
$patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name ASC");
?>
<style>
    .scenario-card { border: 1px solid var(--border); border-radius: 12px; padding: 1.2rem; transition: var(--transition); }
    .scenario-card:hover { box-shadow: var(--shadow-lg); }
    .scenario-card.current { background: linear-gradient(135deg, #f0fdf4, #fff); border-color: #10b981; }
    .scenario-card.simulated { background: linear-gradient(135deg, #eff6ff, #fff); border-color: #3b82f6; }
    .risk-meter { height: 8px; border-radius: 4px; background: #e2e8f0; overflow: hidden; margin: 8px 0; }
    .risk-fill { height: 100%; border-radius: 4px; transition: width 1s ease; }
    .savings-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🔮 محاكي ماذا لو</h1>
    <p class="page-subtitle">What-If Simulator — توقع تأثير التغييرات السريرية على درجة الخطورة</p>
</div>

<!-- Patient Selector -->
<div class="card mb-4">
    <form id="simForm" class="flex flex-wrap gap-3 items-end">
        <div class="field" style="min-width:250px;">
            <label>اختر مريضاً</label>
            <select name="patient_id" id="patientSelect" required>
                <option value="">-- اختر مريض --</option>
                <?php while ($p = $patients->fetch_assoc()): ?>
                <option value="<?php echo $p['patient_id']; ?>"><?php echo escape_output($p['full_name']); ?> (📁<?php echo escape_output($p['file_number']); ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">🔮 محاكاة</button>
    </form>
</div>

<!-- Results Container -->
<div id="simResults">
    <div class="card"><p class="text-muted text-center" style="padding:3rem;">اختر مريضاً وانقر "محاكاة" لعرض السيناريوهات</p></div>
</div>

</div></div></div>

<script>
document.getElementById('simForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const patientId = document.getElementById('patientSelect').value;
    if (!patientId) return;

    const container = document.getElementById('simResults');
    container.innerHTML = '<div class="card"><p class="text-muted text-center" style="padding:3rem;"><div class="spinner" style="margin:0 auto;"></div>جاري المحاكاة...</p></div>';

    fetch(BASE_URL + '/api/analytics.php?action=what_if&patient_id=' + patientId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<div class="card"><p class="text-red text-center" style="padding:3rem;">' + data.error + '</p></div>';
                return;
            }

            let html = `
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-title">👤 ${data.patient_name}</div>
                    </div>
                    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));">
                        <div class="stat-card"><div class="stat-value" style="color:var(--teal);font-size:1.2rem;">${data.current_hba1c || '—'}%</div><div class="stat-label">HbA1c الحالي</div></div>
                        <div class="stat-card"><div class="stat-value" style="color:var(--orange);font-size:1.2rem;">Wagner ${data.current_wagner || '—'}</div><div class="stat-label">Wagner الحالي</div></div>
                        <div class="stat-card"><div class="stat-value" style="color:var(--blue);font-size:1.2rem;">${data.scenarios ? data.scenarios.length : 0}</div><div class="stat-label">سيناريوهات</div></div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">`;

            // Current state card
            const currentRisk = data.scenarios && data.scenarios.length > 0 ? data.scenarios[0].current_risk : 0;
            html += `
                <div class="scenario-card current" style="flex:1;min-width:250px;">
                    <div style="font-weight:700;font-size:1.1rem;margin-bottom:8px;">📊 الوضع الحالي</div>
                    <div style="font-size:2rem;font-weight:900;color:${currentRisk >= 70 ? '#dc2626' : currentRisk >= 40 ? '#f59e0b' : '#10b981'};">${currentRisk}%</div>
                    <div class="risk-meter"><div class="risk-fill" style="width:${Math.min(currentRisk, 100)}%;background:${currentRisk >= 70 ? '#dc2626' : currentRisk >= 40 ? '#f59e0b' : '#10b981'};"></div></div>
                    <div style="font-size:0.85rem;color:var(--text-muted);">درجة الخطورة الحالية<br>HbA1c: ${data.current_hba1c}% | Wagner: ${data.current_wagner}</div>
                </div>`;

            // Scenarios
            if (data.scenarios) {
                data.scenarios.forEach((s, i) => {
                    const improvement = s.current_risk - s.new_risk;
                    html += `
                        <div class="scenario-card simulated" style="flex:1;min-width:250px;">
                            <div style="font-weight:700;font-size:1.1rem;margin-bottom:8px;">💡 ${s.name}</div>
                            <div class="flex items-center gap-2" style="margin-bottom:8px;">
                                <div style="flex:1;text-align:center;">
                                    <div style="font-size:0.75rem;color:var(--text-muted);">قبل</div>
                                    <div style="font-size:1.5rem;font-weight:800;color:#dc2626;">${s.current_risk}%</div>
                                </div>
                                <div style="font-size:1.5rem;color:var(--text-muted);">→</div>
                                <div style="flex:1;text-align:center;">
                                    <div style="font-size:0.75rem;color:var(--text-muted);">بعد</div>
                                    <div style="font-size:1.5rem;font-weight:800;color:#10b981;">${s.new_risk}%</div>
                                </div>
                            </div>
                            <div class="risk-meter">
                                <div class="risk-fill" style="width:${Math.min(s.current_risk, 100)}%;background:#dc2626;"></div>
                                <div class="risk-fill" style="width:${Math.min(s.new_risk, 100)}%;background:#10b981;margin-top:-8px;"></div>
                            </div>
                            <div style="margin-top:8px;">
                                <span class="savings-badge" style="background:#d1fae5;color:#059669;">📉 -${improvement} نقطة خطر</span>
                                ${s.days_saved ? `<span class="savings-badge" style="background:#eff6ff;color:#1d4ed8;margin-right:4px;">⏱ يوفر ~${s.days_saved} يوم شفاء</span>` : ''}
                            </div>
                            <div style="margin-top:6px;font-size:13px;color:var(--text-muted);">${s.detail}</div>
                        </div>`;
                });
            }

            html += '</div>';
            container.innerHTML = html;
        })
        .catch(() => {
            container.innerHTML = '<div class="card"><p class="text-red text-center" style="padding:3rem;">❌ خطأ في تحميل البيانات</p></div>';
        });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
