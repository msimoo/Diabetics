<?php
/**
 * Similar Patients Engine — Find similar patients and treatment recommendations
 */
$page_title = '👥 محرك تشابه المرضى | Similar Patients';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name ASC");
?>
<style>
    .sim-card { border: 1px solid var(--border); border-radius: 12px; padding: 1rem; transition: var(--transition); margin-bottom: 0.6rem; }
    .sim-card:hover { box-shadow: var(--shadow-lg); transform: translateX(-2px); }
    .sim-score { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; }
    .match-tag { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; background: var(--teal-pale); color: var(--teal); }
    .rec-card { padding: 0.75rem; border-radius: 10px; border: 1px solid var(--border); margin-bottom: 0.5rem; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">👥 محرك تشابه المرضى</h1>
    <p class="page-subtitle">Similar Patients Engine — ابحث عن مرضى مشابهين وتوصيات علاجية</p>
</div>

<div class="card mb-4">
    <form id="simForm" class="flex flex-wrap gap-3 items-end">
        <div class="field" style="min-width:250px;">
            <label>اختر مريضاً مرجعياً</label>
            <select name="patient_id" id="patientSelect" required>
                <option value="">-- اختر مريض --</option>
                <?php while ($p = $patients->fetch_assoc()): ?>
                <option value="<?php echo $p['patient_id']; ?>"><?php echo escape_output($p['full_name']); ?> (📁<?php echo escape_output($p['file_number']); ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">👥 بحث</button>
    </form>
</div>

<div id="simResults">
    <div class="card"><p class="text-muted text-center" style="padding:3rem;">اختر مريضاً لرؤية الحالات المشابهة</p></div>
</div>

</div></div></div>

<script>
document.getElementById('simForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const patientId = document.getElementById('patientSelect').value;
    if (!patientId) return;

    const container = document.getElementById('simResults');
    container.innerHTML = '<div class="card"><p class="text-muted text-center" style="padding:3rem;"><div class="spinner" style="margin:0 auto;"></div>جاري البحث...</p></div>';

    fetch(BASE_URL + '/api/analytics.php?action=similar_patients&patient_id=' + patientId)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<div class="card"><p class="text-red text-center" style="padding:3rem;">' + data.error + '</p></div>';
                return;
            }

            let html = `
                <div class="card mb-4">
                    <div class="card-header"><div class="card-title">👤 المريض المرجعي: ${data.source.full_name}</div></div>
                    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));">
                        <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--teal);">${data.source.age || '—'}</div><div class="stat-label">العمر</div></div>
                        <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--orange);">Wagner ${data.source.wagner || '—'}</div><div class="stat-label">Wagner</div></div>
                        <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--red);">${data.source.hba1c || '—'}%</div><div class="stat-label">HbA1c</div></div>
                    </div>
                </div>`;

            // Treatment recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                const best = data.recommendations[0];
                html += `
                <div class="card mb-4" style="background:linear-gradient(135deg,#f0fdf4,#fff);border-color:#10b981;">
                    <div class="card-header"><div class="card-title">💡 توصيات علاجية من حالات مماثلة</div></div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        ${data.recommendations.map(r => `
                            <div class="rec-card" style="${r === data.recommendations[0] ? 'background:#d1fae5;border-color:#10b981;' : ''}">
                                <div class="flex justify-between items-center">
                                    <span style="font-weight:700;">${r.treatment_type}</span>
                                    <span><span class="badge badge-success">${r.avg_improvement ? r.avg_improvement.toFixed(1) + '% تحسن' : '—'}</span> (${r.cnt} مريض)</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            // Similar patients
            html += `<div class="card"><div class="card-header"><div class="card-title">🏆 أكثر 10 مرضى تشابهاً</div></div>`;
            if (data.similar && data.similar.length > 0) {
                html += data.similar.map(s => `
                    <div class="sim-card">
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="sim-score" style="background:${s.similarity_score >= 30 ? '#d1fae5;color:#059669' : s.similarity_score >= 20 ? '#fef3c7;color:#d97706' : '#fee2e2;color:#dc2626'};">${s.similarity_score}</div>
                            <div style="flex:1;min-width:120px;">
                                <div style="font-weight:700;">${s.full_name}</div>
                                <div style="font-size:12px;color:var(--text-muted);">📁 ${s.file_number}</div>
                            </div>
                            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                ${s.wagner !== null ? `<span>⚠️ Wagner <strong>${s.wagner}</strong></span>` : ''}
                                ${s.hba1c ? `<span>🩸 HbA1c <strong>${s.hba1c}%</strong></span>` : ''}
                                ${s.improvement !== null ? `<span class="badge badge-success">✅ ${s.improvement}% تحسن</span>` : ''}
                                ${s.amputation && s.amputation !== 'لا' ? `<span class="badge badge-danger">🦶 بتر</span>` : ''}
                                ${s.gap_days > 90 ? `<span class="badge badge-warning">🚪 ${s.gap_days} يوم</span>` : ''}
                            </div>
                            <div>
                                ${s.matches.slice(0, 2).map(m => `<span class="match-tag">${m}</span>`).join(' ')}
                                ${s.matches.length > 2 ? `<span class="match-tag">+${s.matches.length - 2}</span>` : ''}
                            </div>
                            <a href="${BASE_URL}/modules/patients/view.php?id=${s.patient_id}" class="btn btn-sm btn-secondary">عرض</a>
                        </div>
                    </div>
                `).join('');
            } else {
                html += '<p class="text-muted text-center" style="padding:2rem;">لا توجد نتائج مشابهة كافية</p>';
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
