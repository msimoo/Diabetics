/**
 * Sari Clinic System - Main JavaScript
 * Core utilities and helpers
 */

// === Sidebar Toggle ===
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    if (!sidebar) return;
    
    const willCollapse = !sidebar.classList.contains('collapsed');
    
    // Save submenu state before collapsing
    if (willCollapse) {
        const submenu = document.getElementById('analyticsSubmenu');
        if (submenu) {
            try { localStorage.setItem('analytics-submenu-before-collapse', submenu.style.display !== 'none' ? '1' : '0'); } catch(e) {}
        }
    }
    
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
    } else {
        sidebar.classList.toggle('collapsed');
        if (mainContent) {
            mainContent.classList.toggle('expanded');
        }
        
        // Restore submenu state after expanding
        if (!willCollapse) {
            const submenu = document.getElementById('analyticsSubmenu');
            const arrow = document.getElementById('analyticsArrow');
            if (submenu) {
                try {
                    const wasOpen = localStorage.getItem('analytics-submenu-before-collapse');
                    if (wasOpen === '1') {
                        submenu.style.display = 'block';
                        if (arrow) arrow.style.transform = 'rotate(90deg)';
                    }
                } catch(e) {}
            }
        }
        
        // Save preference
        try { localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0'); } catch(e) {}
    }
}

// Analytics submenu toggle
function toggleAnalyticsMenu() {
    const submenu = document.getElementById('analyticsSubmenu');
    const arrow = document.getElementById('analyticsArrow');
    if (submenu) {
        const isOpen = submenu.style.display !== 'none';
        submenu.style.display = isOpen ? 'none' : 'block';
        if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
        try { localStorage.setItem('analytics-submenu-open', isOpen ? '0' : '1'); } catch(e) {}
    }
}

// === Restore sidebar states on page load ===
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Restore sidebar state
        const collapsed = localStorage.getItem('sidebar-collapsed');
        if (collapsed === '1') {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            if (sidebar) sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('expanded');
        }
        
        // Note: Analytics submenu state is restored by sidebar.php's inline script
        // (which has access to PHP $analytics_active variable)
    } catch(e) {}
});

// === Toast Notifications ===
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3500);
}

// === Modal Functions ===
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show');
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

// === Loading States ===
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = '<div class="flex justify-center mt-5"><div class="spinner"></div></div>';
    }
}

function removeLoading(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        const spinner = container.querySelector('.spinner');
        if (spinner) {
            spinner.remove();
        }
    }
}

// === Confirm Dialog ===
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// === Format Numbers ===
function formatNumber(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

// === Date Helpers ===
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// === Form Auto-Save Indicator ===
function showAutoSaveIndicator(element, message = 'تم الحفظ') {
    const indicator = document.createElement('span');
    indicator.className = 'auto-save-indicator';
    indicator.textContent = '✓ ' + message;
    indicator.style.cssText = `
        color: #10b981;
        font-size: 12px;
        margin-right: 8px;
        animation: fadeIn 0.3s ease;
    `;
    
    if (element) {
        element.appendChild(indicator);
        setTimeout(() => indicator.remove(), 2000);
    }
}

// === Navbar Search ===
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('navbarSearchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    
    if (searchInput && searchDropdown) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchDropdown.classList.remove('open');
                return;
            }

            searchTimeout = setTimeout(() => {
                searchDropdown.innerHTML = '<div class="search-no-results"><div class="spinner" style="margin:8px auto;width:24px;height:24px;border-width:2px;"></div></div>';
                searchDropdown.classList.add('open');

                fetch(BASE_URL + '/api/search.php?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        if (data.length === 0) {
                            searchDropdown.innerHTML = '<div class="search-no-results">🔍 لا توجد نتائج</div>';
                            return;
                        }
                        
                        searchDropdown.innerHTML = data.map(p => `
                            <a href="${BASE_URL}/modules/patients/view.php?id=${p.patient_id}" class="search-result-item">
                                <span style="font-size:18px;">👤</span>
                                <div>
                                    <div class="result-name">${p.full_name}</div>
                                    <div class="result-file">📁 ${p.file_number} ${p.city ? '| ' + p.city : ''}</div>
                                </div>
                            </a>
                        `).join('');
                    })
                    .catch(() => {
                        searchDropdown.innerHTML = '<div class="search-no-results">❌ خطأ في البحث</div>';
                    });
            }, 300);
        });

        // Close dropdown on blur
        searchInput.addEventListener('blur', function() {
            setTimeout(() => searchDropdown.classList.remove('open'), 200);
        });

        // Reopen on focus if there's a query
        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 2) {
                searchDropdown.classList.add('open');
            }
        });
    }
});

// === Dark Mode Toggle ===
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        // Set initial icon
        const currentTheme = localStorage.getItem('clinic-theme');
        if (currentTheme === 'dark') {
            themeToggle.textContent = '☀️';
            themeToggle.title = 'الوضع النهاري';
        }

        themeToggle.addEventListener('click', function() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('clinic-theme', 'light');
                this.textContent = '🌙';
                this.title = 'الوضع الليلي';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('clinic-theme', 'dark');
                this.textContent = '☀️';
                this.title = 'الوضع النهاري';
            }
        });
    }
});

// === Table row click navigation ===
document.addEventListener('DOMContentLoaded', function() {
    // Clickable table rows
    document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function() {
            const url = this.dataset.href;
            if (url) window.location.href = url;
        });
    });

    // Auto-calculate age from DOB
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');
    if (dobInput && ageInput) {
        dobInput.addEventListener('change', function() {
            if (this.value) {
                const birth = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                ageInput.value = age;
            }
        });
    }

    // Initialize animations
    document.querySelectorAll('.card-fade-in').forEach((card, i) => {
        card.style.animationDelay = (i * 0.05) + 's';
    });
});
