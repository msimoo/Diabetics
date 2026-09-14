/**
 * Sari Clinic System - Live Search
 */

let searchTimeout = null;

function initPatientSearch(inputId, resultsId) {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    
    if (!input || !results) return;

    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            results.innerHTML = '';
            results.classList.remove('show');
            return;
        }

        searchTimeout = setTimeout(() => {
            searchPatients(query, results);
        }, 300);
    });

    // Close on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            results.classList.remove('show');
        }
    });
}

function searchPatients(query, resultsContainer) {
    resultsContainer.innerHTML = '<div style="padding:12px;text-align:center;color:#94a3b8;">جاري البحث...</div>';
    resultsContainer.classList.add('show');

    fetch(BASE_URL + '/api/search.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                resultsContainer.innerHTML = '<div style="padding:12px;text-align:center;color:#94a3b8;">لا توجد نتائج</div>';
                return;
            }

            let html = '';
            data.forEach(patient => {
                html += `
                    <a href="${BASE_URL}/modules/patients/view.php?id=${patient.patient_id}" class="search-result-item">
                        <div class="search-result-name">${patient.full_name}</div>
                        <div class="search-result-details">
                            <span>📁 ${patient.file_number}</span>
                            ${patient.age ? `<span>👤 ${patient.age} سنة</span>` : ''}
                            <span>📞 ${patient.phone_primary || '—'}</span>
                        </div>
                    </a>
                `;
            });
            resultsContainer.innerHTML = html;
        })
        .catch(err => {
            resultsContainer.innerHTML = '<div style="padding:12px;text-align:center;color:#ef4444;">خطأ في البحث</div>';
            console.error('Search error:', err);
        });
}

function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}

// Auto-hide search results
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-box')) {
        document.querySelectorAll('.search-results').forEach(el => {
            el.classList.remove('show');
        });
    }
});
