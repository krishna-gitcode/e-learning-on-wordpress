document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Search Overlay Logic
    const trigger = document.querySelector('.cppm-search-trigger');
    const overlay = document.querySelector('.cppm-search-overlay');
    const input   = document.querySelector('.cppm-search-box input[type="search"]');

    if (trigger && overlay) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            if(input) setTimeout(() => input.focus(), 100);
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    }

    // 2. Grid vs. List View Toggle Logic
    const viewBtns = document.querySelectorAll('.cppm-view-btn');
    const productGrid = document.querySelector('ul.products');
    
    // Check localStorage to remember user's preference
    const savedView = localStorage.getItem('cppm_shop_view') || 'grid';
    applyView(savedView);

    viewBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const view = this.getAttribute('data-view');
            applyView(view);
            localStorage.setItem('cppm_shop_view', view);
        });
    });

    function applyView(view) {
        if (!productGrid) return;
        
        viewBtns.forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector('.cppm-view-btn[data-view="'+view+'"]');
        if (activeBtn) activeBtn.classList.add('active');
        
        if (view === 'list') { 
            productGrid.classList.add('cppm-list-view'); 
        } else { 
            productGrid.classList.remove('cppm-list-view'); 
        }
    }
});