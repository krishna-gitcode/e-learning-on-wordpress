document.addEventListener('DOMContentLoaded', function() {
    
    // Ensure the localized data object exists
    if (typeof cppmDashboardData === 'undefined') return;

    const navItems = document.querySelectorAll('.cppm-dash-nav-item');
    const contentArea = document.getElementById('cppm-dash-content-area');
    
    if (!navItems.length || !contentArea) return;

    const ajaxUrl = cppmDashboardData.ajaxUrl;
    const ajaxNonce = cppmDashboardData.ajaxNonce;

    // Loading Animation HTML
    const loaderHTML = '<div class="cppm-dash-loader-wrap"><div class="cppm-dash-spinner"></div></div>';

    // Tab Click Event Listeners
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            
            if (this.classList.contains('active')) return;

            // Update active states
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');

            const targetView = this.getAttribute('data-view');
            loadDashboardTab(targetView);
        });
    });

    // The AJAX Engine
    function loadDashboardTab(view) {
        
        // Inject loader and disable interaction slightly
        contentArea.style.position = 'relative';
        contentArea.insertAdjacentHTML('beforeend', loaderHTML);

        const formData = new FormData();
        formData.append('action', 'cppm_load_dashboard_tab');
        formData.append('view', view);
        formData.append('security', ajaxNonce);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            contentArea.innerHTML = html;
        })
        .catch(error => {
            console.error('Dashboard Error:', error);
            contentArea.innerHTML = '<div style="padding:40px; text-align:center; color:#ef4444;">Failed to load data. Please refresh the page.</div>';
        });
    }

    // Initial Load (Defaults to 'courses' tab)
    loadDashboardTab('courses');
});