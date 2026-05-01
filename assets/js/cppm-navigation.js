document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Handle Dropdown Toggles Safely (No Inline JS)
    const authWraps = document.querySelectorAll('.cppm-auth-wrap');
    
    authWraps.forEach(function(wrap) {
        wrap.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent immediate document click trigger
            const dropdown = this.querySelector('.cppm-dropdown-content');
            if (dropdown) {
                // Close any other open dropdowns first
                document.querySelectorAll('.cppm-dropdown-content.show').forEach(function(openDrop) {
                    if (openDrop !== dropdown) {
                        openDrop.classList.remove('show');
                    }
                });
                dropdown.classList.toggle('show');
            }
        });
    });

    // 2. Close the dropdown if the user clicks anywhere outside of it
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cppm-auth-wrap')) {
            document.querySelectorAll('.cppm-dropdown-content.show').forEach(function(dropdown) {
                dropdown.classList.remove('show');
            });
        }
    });

});