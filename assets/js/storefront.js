document.addEventListener('DOMContentLoaded', function() {
    const productContainer = document.getElementById('cppm-store-products');
    const catButtons = document.querySelectorAll('.cppm-cat-item');
    const desktopSortSelect = document.querySelector('.cppm-main-sort-select');
    const mobileSortSelect = document.querySelector('.cppm-mobile-sort-select');
    
    // Modal Elements
    const filterToggleDesktop = document.getElementById('cppm-filter-toggle');
    const filterToggleMobile = document.getElementById('cppm-filter-toggle-mobile');
    const filterOverlay = document.getElementById('cppm-filter-overlay');
    const closeFiltersBtn = document.getElementById('cppm-close-filters');
    const applyFiltersBtn = document.getElementById('cppm-apply-filters-btn');
    
    // Read Localized PHP Data
    if (typeof cppmStorefrontData === 'undefined') return;
    const ajaxUrl = cppmStorefrontData.ajaxUrl;
    const ajaxNonce = cppmStorefrontData.ajaxNonce;

    let currentCategory = 'all';
    let currentOrderBy = 'popularity';
    let currentPage = 1;
    let isLoadingProducts = false;
    let isFetchingMore = false;
    let hasMorePosts = true;

    // Modal Toggle Logic
    function openModal() {
        filterOverlay.classList.add('open');
        document.body.style.overflow = 'hidden'; 
    }
    function closeModal() {
        filterOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if(filterToggleDesktop) filterToggleDesktop.addEventListener('click', openModal);
    if(filterToggleMobile) filterToggleMobile.addEventListener('click', openModal);
    if(closeFiltersBtn) closeFiltersBtn.addEventListener('click', closeModal);
    
    if(filterOverlay) {
        filterOverlay.addEventListener('click', function(e) {
            if(e.target === filterOverlay) {
                closeModal();
            }
        });
    }

    // Product Loader Engine
    function loadStoreProducts(resetPaged = true, isLazy = false) {
        if (isLoadingProducts || isFetchingMore) return;

        if (resetPaged) {
            currentPage = 1;
            isLoadingProducts = true;
            hasMorePosts = true;
            productContainer.innerHTML = '<div class="cppm-store-loader"><div class="cppm-spinner"></div></div>';
        } else if (isLazy && hasMorePosts) {
            isFetchingMore = true;
            currentPage++;
            const grid = productContainer.querySelector('.products.cppm-strict-grid');
            if(grid) grid.insertAdjacentHTML('beforeend', '<li class="cppm-load-more-loader"><div class="cppm-spinner"></div></li>');
        } else {
            return; 
        }

        const formData = new FormData();
        formData.append('action', resetPaged ? 'cppm_load_store_products' : 'cppm_lazy_load_more');
        if (!resetPaged) formData.append('paged', currentPage);

        formData.append('category', currentCategory);
        formData.append('orderby', currentOrderBy);
        
        formData.append('min_price', document.getElementById('filter-min-price') ? document.getElementById('filter-min-price').value : '');
        formData.append('max_price', document.getElementById('filter-max-price') ? document.getElementById('filter-max-price').value : '');
        formData.append('min_rating', document.getElementById('filter-min-rating') ? document.getElementById('filter-min-rating').value : '');
        formData.append('on_sale', (document.getElementById('filter-on-sale') && document.getElementById('filter-on-sale').checked) ? 'true' : 'false');
        
        formData.append('security', ajaxNonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(response => resetPaged ? response.text() : response.json())
        .then(data => {
            if (resetPaged) {
                productContainer.innerHTML = data;
            } else {
                const loader = productContainer.querySelector('.cppm-load-more-loader');
                if (loader) loader.remove();
                if (data.success && data.data.content) {
                    const grid = productContainer.querySelector('.products.cppm-strict-grid');
                    if(grid) grid.insertAdjacentHTML('beforeend', data.data.content);
                    hasMorePosts = data.data.has_more;
                }
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            if (resetPaged) productContainer.innerHTML = '<div style="text-align:center; padding: 20px; color:red;">Connection error. Please try again.</div>';
        })
        .finally(() => {
            isLoadingProducts = false;
            isFetchingMore = false;
        });
    }

    if(applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', function() {
            closeModal();
            loadStoreProducts(true);
        });
    }

    catButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.classList.contains('active')) return;
            catButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.getAttribute('data-category');
            loadStoreProducts(true);
        });
    });

    function handleSortChange(e) {
        currentOrderBy = e.target.value;
        if (desktopSortSelect) desktopSortSelect.value = currentOrderBy;
        if (mobileSortSelect) mobileSortSelect.value = currentOrderBy;
        loadStoreProducts(true);
    }

    if (desktopSortSelect) desktopSortSelect.addEventListener('change', handleSortChange);
    if (mobileSortSelect) mobileSortSelect.addEventListener('change', handleSortChange);

    // Intersection Observer for Infinite Scroll
    const observerOptions = { root: null, rootMargin: '0px 0px 300px 0px', threshold: 0.1 };
    const loadMoreObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !isLoadingProducts && !isFetchingMore && hasMorePosts) {
                loadStoreProducts(false, true); 
            }
        });
    }, observerOptions);

    function initialStoreLoad() {
        if (!productContainer) return;
        currentPage = 1; isLoadingProducts = true; hasMorePosts = true;
        productContainer.innerHTML = '<div class="cppm-store-loader"><div class="cppm-spinner"></div></div>';
        const formData = new FormData();
        formData.append('action', 'cppm_load_store_products'); 
        formData.append('category', currentCategory);
        formData.append('orderby', currentOrderBy);
        formData.append('security', ajaxNonce);

        fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(response => response.text()) 
        .then(html => {
            productContainer.innerHTML = html;
            setTimeout(() => {
                const grid = productContainer.querySelector('.products.cppm-strict-grid');
                if (grid) loadMoreObserver.observe(grid); 
            }, 100);
        })
        .finally(() => { isLoadingProducts = false; });
    }

    initialStoreLoad();
});