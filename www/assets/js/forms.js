/**
 * Sari Clinic System - Form Handling
 * Form validation, AJAX submission, auto-calculations
 */

// === Form Validation ===
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#ef4444';
            field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            isValid = false;

            // Show error
            const errorEl = field.parentElement.querySelector('.field-error');
            if (!errorEl) {
                const error = document.createElement('span');
                error.className = 'field-error';
                error.textContent = 'هذا الحقل مطلوب';
                error.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 2px;';
                field.parentElement.appendChild(error);
            }
        } else {
            field.style.borderColor = '';
            field.style.boxShadow = '';
            const errorEl = field.parentElement.querySelector('.field-error');
            if (errorEl) errorEl.remove();
        }
    });

    return isValid;
}

// Clear validation on input
document.addEventListener('input', function(e) {
    if (e.target.hasAttribute('required')) {
        e.target.style.borderColor = '';
        e.target.style.boxShadow = '';
        const error = e.target.parentElement.querySelector('.field-error');
        if (error) error.remove();
    }
});

// === Auto-Calculate BMI ===
document.addEventListener('DOMContentLoaded', function() {
    const weightInput = document.getElementById('weight');
    const heightInput = document.getElementById('height');
    const bmiInput = document.getElementById('bmi');

    if (weightInput && heightInput && bmiInput) {
        function calculateBMI() {
            const weight = parseFloat(weightInput.value);
            const height = parseFloat(heightInput.value);
            
            if (weight && height && height > 0) {
                const heightM = height / 100;
                const bmi = weight / (heightM * heightM);
                bmiInput.value = bmi.toFixed(1);
                
                // Color-code BMI
                let color = '#10b981'; // normal
                let status = 'طبيعي';
                
                if (bmi < 18.5) { color = '#f59e0b'; status = 'نقص وزن'; }
                else if (bmi < 25) { color = '#10b981'; status = 'طبيعي'; }
                else if (bmi < 30) { color = '#f97316'; status = 'زيادة وزن'; }
                else { color = '#ef4444'; status = 'سمنة'; }
                
                bmiInput.style.color = color;
                bmiInput.title = `BMI: ${bmi.toFixed(1)} - ${status}`;
            }
        }

        weightInput.addEventListener('input', calculateBMI);
        heightInput.addEventListener('input', calculateBMI);
    }

    // === Blood Pressure auto-parse ===
    const bpInput = document.getElementById('blood_pressure_text');
    const systolicInput = document.getElementById('blood_pressure_systolic');
    const diastolicInput = document.getElementById('blood_pressure_diastolic');
    
    if (bpInput && systolicInput && diastolicInput) {
        bpInput.addEventListener('input', function() {
            const parts = this.value.split('/');
            if (parts.length === 2) {
                systolicInput.value = parseInt(parts[0]) || '';
                diastolicInput.value = parseInt(parts[1]) || '';
            }
        });
    }

    // === HbA1c Analysis ===
    const hba1cInput = document.getElementById('hba1c_value');
    const hba1cResult = document.getElementById('hba1c_result');
    
    if (hba1cInput && hba1cResult) {
        hba1cInput.addEventListener('input', function() {
            const val = parseFloat(this.value);
            if (val) {
                let status = '', color = '';
                if (val < 5.7) { status = 'طبيعي'; color = '#10b981'; }
                else if (val < 6.5) { status = 'مقدمات السكري'; color = '#f59e0b'; }
                else { status = 'سكري'; color = '#ef4444'; }
                
                hba1cResult.innerHTML = `<span style="color:${color};font-weight:700;">${status}</span>`;
            } else {
                hba1cResult.innerHTML = '';
            }
        });
    }

    // === Auto-fill patient data when selected ===
    const patientSelect = document.getElementById('patient_id');
    if (patientSelect) {
        patientSelect.addEventListener('change', function() {
            const id = this.value;
            if (id) {
                fetch(BASE_URL + '/api/patients.php?action=get&id=' + id)
                    .then(res => res.json())
                    .then(data => {
                        if (data.patient_id) {
                            const info = document.getElementById('patient_info');
                            if (info) {
                                info.innerHTML = `
                                    <div class="alert alert-info">
                                        <strong>${data.full_name}</strong> - ${data.file_number}
                                        ${data.age ? '| العمر: ' + data.age : ''}
                                    </div>
                                `;
                            }
                        }
                    })
                    .catch(err => console.error('Error fetching patient:', err));
            }
        });
    }
});

// === AJAX Form Submit ===
function submitFormAjax(formId, callback) {
    const form = document.getElementById(formId);
    if (!form) return;

    if (!validateForm(formId)) return;

    const formData = new FormData(form);
    const url = form.getAttribute('action') || window.location.href;

    // Show loading
    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;margin-left:6px;vertical-align:middle;"></span> جارٍ الحفظ...';
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '💾 حفظ';
        }

        if (data.success) {
            showToast(data.message || 'تم الحفظ بنجاح', 'success');
            if (callback) callback(data);
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    })
    .catch(err => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '💾 حفظ';
        }
        showToast('خطأ في الاتصال بالخادم', 'error');
        console.error('AJAX Error:', err);
    });
}
