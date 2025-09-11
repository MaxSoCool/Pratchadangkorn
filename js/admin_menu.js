document.addEventListener('DOMContentLoaded', function() {
    // Highlight active sidebar link
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('main_tab') || 'dashboard_admin'; // Default to dashboard_admin
    const navLinks = document.querySelectorAll('.admin-sidebar .nav-link'); // ใช้ .admin-sidebar เพื่อความเฉพาะเจาะจง

    navLinks.forEach(link => {
        link.classList.remove('active'); // Remove active from all
        if (link.href.includes(`main_tab=${currentTab}`)) {
            link.classList.add('active'); // Add active to current tab
        }
    });
});