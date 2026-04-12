<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'ebook_reader', 'cppm_render_ebook_reader' );
function cppm_render_ebook_reader( $atts ) {
    $atts = shortcode_atts( array( 'id' => '' ), $atts );
    if ( empty( $atts['id'] ) ) return 'Missing E-Book ID';

    $ebook_id = intval( $atts['id'] );
    $prod_id = get_post_meta( $ebook_id, '_cppm_ebook_required_product', true );
    $docs_json = get_post_meta( $ebook_id, '_cppm_ebook_docs_json', true );
    
    if ( empty($docs_json) ) { $docs_json = '[]'; }
    $current_user = wp_get_current_user();

    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; background:#fff3f3; border-radius:12px; border:1px solid #fecaca;"><h3 style="color:#dc2626; margin-top:0;">Locked</h3><p>Please log in to read this material.</p></div>';
    }
    
    $has_access = false;
    $can_download = false;
    
    if ( empty($prod_id) ) {
        $has_access = true; 
    } elseif ( function_exists('wc_get_orders') ) {
        $customer_orders = wc_get_orders( array(
            'customer_id' => $current_user->ID,
            'status'      => array('wc-completed'),
            'limit'       => -1,
        ) );
        foreach ( $customer_orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                if ( $item->get_product_id() == $prod_id ) {
                    $has_access = true;
                    if ( $order->get_meta( '_cppm_grant_pdf_downloads' ) === 'yes' ) {
                        $can_download = true;
                    }
                    break 2;
                }
            }
        }
    }

    if ( !$has_access ) {
        return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">Enrollment Required</h3><p>You must purchase this course to access the library.</p></div>';
    }

    $progress_data = get_user_meta($current_user->ID, '_cppm_ebook_progress_' . $ebook_id, true);
    if ( !is_array($progress_data) ) { $progress_data = array(); }

    $ui_brand = get_option('cppm_ui_btn_color', '#2563eb');
    $uid = uniqid('eb_');
    $watermark_text = esc_js( $current_user->display_name . ' | ' . $current_user->user_email );

    // Premium SVG Icons
    $icon_menu = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
    $icon_prev = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
    $icon_next = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
    $icon_zoom_out = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>';
    $icon_zoom_in = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>';
    $icon_moon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
    $icon_download = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
    $icon_fullscreen = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>';
    
    ob_start();
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';</script>

    <style>
        #ebook-<?php echo $uid; ?> * { box-sizing: border-box; }
        #ebook-<?php echo $uid; ?> { 
            --brand: <?php echo $ui_brand; ?>; 
            --bg-main: #f8fafc; --bg-sec: #ffffff; --bg-hover: #f1f5f9;
            --text-main: #0f172a; --text-sec: #64748b; --border: #e2e8f0; --reader-bg: #e2e8f0;
            display: flex; flex-direction: column; font-family: 'Jost', 'Poppins', sans-serif;
            border: 1px solid var(--border); border-radius: 12px; overflow: hidden;
            height: 85vh; min-height: 600px; position: relative; background: var(--bg-main);
            transition: background 0.3s, color 0.3s; margin: 20px 0; width: 100% !important;
        }

        #ebook-<?php echo $uid; ?>.cppm-dark-mode {
            --bg-main: #0f172a; --bg-sec: #1e293b; --bg-hover: #334155;
            --text-main: #f8fafc; --text-sec: #94a3b8; --border: #334155; --reader-bg: #020617;
        }

        .cppm-eb-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: var(--bg-sec); border-bottom: 1px solid var(--border); z-index: 10; flex-wrap: wrap; gap: 15px; }
        .cppm-eb-title { font-size: 18px; font-weight: 600; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 10px; }
        .cppm-eb-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .cppm-eb-btn { background: var(--bg-hover); border: none; color: var(--text-main); width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .cppm-eb-btn:hover { background: var(--border); }
        .cppm-eb-btn svg { flex-shrink: 0; }
        .cppm-eb-menu-toggle { display: none; }

        .cppm-eb-body { display: flex; flex: 1; overflow: hidden; position: relative; }
        .cppm-eb-sidebar { width: 320px; background: var(--bg-sec); border-right: 1px solid var(--border); display: flex; flex-direction: column; transition: transform 0.3s ease; position: relative; z-index: 5; }
        .cppm-eb-accordion { flex: 1; overflow-y: auto; padding: 10px; }
        
        .cppm-eb-doc-item { margin-bottom: 8px; }
        .cppm-eb-doc-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: var(--bg-hover); border-radius: 8px; cursor: pointer; font-weight: 600; color: var(--text-main); border: 1px solid transparent; transition: 0.2s; font-size: 14px; }
        .cppm-eb-doc-header:hover { border-color: var(--border); }
        .cppm-eb-doc-header.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        
        .cppm-eb-chapter-list { list-style: none; padding: 0 0 0 15px; margin: 5px 0 0 0; display: none; border-left: 2px solid var(--border); margin-left: 20px; }
        .cppm-eb-chapter-list.open { display: block; }
        .cppm-eb-chapter-item { padding: 8px 10px; cursor: pointer; font-size: 13px; color: var(--text-sec); border-radius: 6px; margin-bottom: 2px; transition: 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .cppm-eb-chapter-item:hover { background: var(--bg-hover); color: var(--text-main); }
        .cppm-eb-chapter-item.active { color: var(--brand); font-weight: 600; background: rgba(37, 99, 235, 0.05); }

        .cppm-eb-reader-wrapper { flex: 1; display: flex; flex-direction: column; position: relative; min-width: 0; background: var(--reader-bg); overflow: hidden; }
        .cppm-eb-reader { flex: 1; overflow: auto; padding: 20px 20px 100px 20px; position: relative; user-select: none; text-align: center; }
        
        .cppm-eb-floating-nav {
            position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: var(--bg-sec); border: 1px solid var(--border); border-radius: 50px;
            padding: 8px 15px; display: flex; align-items: center; gap: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 20;
            transition: background 0.3s, border 0.3s;
        }
        .cppm-eb-nav-arrow { background: transparent; border: none; color: var(--text-main); cursor: pointer; padding: 5px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; }
        .cppm-eb-nav-arrow:hover { background: var(--bg-hover); color: var(--brand); }
        .cppm-eb-nav-arrow svg { width: 22px; height: 22px; }
        
        /* FIX: Reset padding to 0 so all digits are visible in the narrow box */
        .cppm-eb-page-input { 
            display: inline-block !important;
            width: 65px !important; 
            max-width: 65px !important;
            min-width: 65px !important;
            text-align: center !important; 
            padding: 6px 0 !important; /* Forces 0 horizontal padding */
            border: 1px solid var(--border) !important; 
            border-radius: 6px !important; 
            background: var(--bg-main) !important; 
            color: var(--text-main) !important; 
            font-weight: bold !important; 
            font-family: inherit !important; 
            font-size: 16px !important; 
            height: 34px !important;
            line-height: normal !important;
            margin: 0 !important;
            user-select: text !important; 
            -webkit-user-select: text !important;
            touch-action: manipulation !important; 
            pointer-events: auto !important;
        }
        .cppm-eb-page-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        .cppm-eb-page-text { font-size: 15px; font-weight: 600; color: var(--text-sec); white-space: nowrap; }

        .cppm-eb-canvas-wrap { display: inline-block; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.15); background: #ffffff; margin-bottom: 20px; transition: transform 0.1s ease; text-align: left; }
        .cppm-eb-canvas { display: block; height: auto; }
        #ebook-<?php echo $uid; ?>.cppm-dark-mode .cppm-eb-canvas-wrap { filter: invert(90%) hue-rotate(180deg); }

        .cppm-eb-watermark { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 2; display: flex; flex-wrap: wrap; align-content: space-around; justify-content: space-around; overflow: hidden; opacity: 0.08; }
        .cppm-eb-wm-text { transform: rotate(-30deg); font-size: 24px; font-weight: 900; color: #000; white-space: nowrap; }

        .cppm-eb-loader { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; border: 4px solid var(--border); border-top-color: var(--brand); border-radius: 50%; animation: cppm-spin 1s linear infinite; z-index: 10; display: none; }
        @keyframes cppm-spin { to { transform: rotate(360deg); } }

        @media (max-width: 1024px) {
            .cppm-eb-menu-toggle { display: flex; }
            .cppm-eb-sidebar { position: absolute; left: 0; top: 0; height: 100%; transform: translateX(-100%); box-shadow: 5px 0 15px rgba(0,0,0,0.1); }
            .cppm-eb-sidebar.open { transform: translateX(0); }
            .cppm-eb-header { justify-content: space-between; }
        }
    </style>

    <div id="ebook-<?php echo $uid; ?>" data-docs="<?php echo esc_attr($docs_json); ?>">
        <div class="cppm-eb-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" class="cppm-eb-btn cppm-eb-menu-toggle" id="menu-btn-<?php echo $uid; ?>"><?php echo $icon_menu; ?></button>
                <h3 class="cppm-eb-title" id="active-title-<?php echo $uid; ?>"><?php echo get_the_title($ebook_id); ?></h3>
            </div>
            
            <div class="cppm-eb-controls">
                <button type="button" class="cppm-eb-btn" id="zoom-out-<?php echo $uid; ?>" title="Zoom Out"><?php echo $icon_zoom_out; ?></button>
                <button type="button" class="cppm-eb-btn" id="zoom-in-<?php echo $uid; ?>" title="Zoom In"><?php echo $icon_zoom_in; ?></button>
                <button type="button" class="cppm-eb-btn" id="dark-btn-<?php echo $uid; ?>" title="Toggle Night Mode"><?php echo $icon_moon; ?></button>
                
                <?php if ($can_download): ?>
                    <button type="button" class="cppm-eb-btn" id="download-btn-<?php echo $uid; ?>" title="Download PDF" style="background:#10b981; color:#fff; border-color:#10b981;"><?php echo $icon_download; ?></button>
                <?php endif; ?>
                
                <button type="button" class="cppm-eb-btn" id="fs-btn-<?php echo $uid; ?>" title="Full Screen"><?php echo $icon_fullscreen; ?></button>
            </div>
        </div>

        <div class="cppm-eb-body">
            <div class="cppm-eb-sidebar" id="sidebar-<?php echo $uid; ?>">
                <div style="padding:15px; border-bottom:1px solid var(--border); font-weight:700; color:var(--text-main); display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    Document Library
                </div>
                <div class="cppm-eb-accordion" id="accordion-<?php echo $uid; ?>"></div>
            </div>

            <div class="cppm-eb-reader-wrapper">
                <div class="cppm-eb-reader" id="reader-<?php echo $uid; ?>" <?php echo $can_download ? '' : 'oncontextmenu="return false;"'; ?>> 
                    <div class="cppm-eb-loader" id="loader-<?php echo $uid; ?>"></div>
                    <div class="cppm-eb-canvas-wrap" id="canvas-wrap-<?php echo $uid; ?>">
                        <canvas id="pdf-canvas-<?php echo $uid; ?>" class="cppm-eb-canvas"></canvas>
                        <div class="cppm-eb-watermark">
                            <?php for($i=0; $i<6; $i++): ?><div class="cppm-eb-wm-text"><?php echo $watermark_text; ?></div><?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="cppm-eb-floating-nav">
                    <button type="button" class="cppm-eb-nav-arrow" id="prev-page-<?php echo $uid; ?>" title="Previous Page"><?php echo $icon_prev; ?></button>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" class="cppm-eb-page-input" id="page-input-<?php echo $uid; ?>" value="1">
                        <span class="cppm-eb-page-text" id="page-total-<?php echo $uid; ?>"> / -</span>
                    </div>
                    <button type="button" class="cppm-eb-nav-arrow" id="next-page-<?php echo $uid; ?>" title="Next Page"><?php echo $icon_next; ?></button>
                </div>
            </div>
            
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var ajaxUrl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";
        var uid = "<?php echo $uid; ?>";
        var eid = "<?php echo $ebook_id; ?>";
        
        var svgSun = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';
        var svgMoon = '<?php echo $icon_moon; ?>';
        var svgChevDown = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        var svgChevRight = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        var svgDoc = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';

        var wrap = document.getElementById('ebook-' + uid);
        var docs = JSON.parse(wrap.getAttribute('data-docs') || '[]');
        var progressData = <?php echo json_encode($progress_data); ?>; 
        
        if (docs.length === 0) {
            document.getElementById('reader-' + uid).innerHTML = '<p style="padding:20px;">No documents found in this library.</p>';
            return;
        }

        var activeDocIndex = 0;
        var activePageNum = progressData[0] || 1; 
        
        var canvas = document.getElementById('pdf-canvas-' + uid);
        var ctx = canvas.getContext('2d');
        var canvasWrap = document.getElementById('canvas-wrap-' + uid);
        var readerArea = document.getElementById('reader-' + uid);
        var loader = document.getElementById('loader-' + uid);
        var accordion = document.getElementById('accordion-' + uid);
        var titleBar = document.getElementById('active-title-' + uid);
        var dlBtn = document.getElementById('download-btn-' + uid);
        
        var prevBtn = document.getElementById('prev-page-' + uid);
        var nextBtn = document.getElementById('next-page-' + uid);
        var pageInput = document.getElementById('page-input-' + uid);
        var pageTotal = document.getElementById('page-total-' + uid);

        var pdfDoc = null;
        var pageIsRendering = false;
        var pageNumPending = null;
        var currentScale = 1.0; 

        if (localStorage.getItem('cppm_theme') === 'dark') {
            wrap.classList.add('cppm-dark-mode');
            document.getElementById('dark-btn-' + uid).innerHTML = svgSun;
        }

        function buildAccordion() {
            accordion.innerHTML = '';
            docs.forEach(function(doc, dIdx) {
                var isOpen = (dIdx === activeDocIndex);
                var arrowIcon = doc.chapters.length > 0 ? (isOpen ? svgChevDown : svgChevRight) : '';
                
                var html = `<div class="cppm-eb-doc-item">
                    <div class="cppm-eb-doc-header ${isOpen ? 'active' : ''}" data-didx="${dIdx}">
                        <span style="display:flex; align-items:center; gap:8px;">${svgDoc} ${doc.title || 'Document ' + (dIdx+1)}</span>
                        <span>${arrowIcon}</span>
                    </div>`;
                
                if (doc.chapters.length > 0) {
                    html += `<ul class="cppm-eb-chapter-list ${isOpen ? 'open' : ''}" id="chap-list-${uid}-${dIdx}">`;
                    doc.chapters.forEach(function(chap, cIdx) {
                        html += `<li class="cppm-eb-chapter-item" data-didx="${dIdx}" data-page="${chap.page}">
                            <span>${chap.title}</span> <span style="opacity:0.6;">p.${chap.page}</span>
                        </li>`;
                    });
                    html += `</ul>`;
                }
                html += `</div>`;
                accordion.insertAdjacentHTML('beforeend', html);
            });
        }

        function loadPDF(dIdx, targetPage = null) {
            if (!docs[dIdx] || !docs[dIdx].url) return;
            activeDocIndex = dIdx;
            titleBar.innerHTML = svgDoc + ' ' + docs[dIdx].title;
            activePageNum = targetPage || progressData[dIdx] || 1;
            
            if (dlBtn) { dlBtn.onclick = function() { window.open(docs[dIdx].url, '_blank'); }; }

            loader.style.display = 'block';
            canvasWrap.style.display = 'none';

            pdfjsLib.getDocument(docs[dIdx].url).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                if(activePageNum > pdfDoc.numPages) activePageNum = pdfDoc.numPages;
                canvasWrap.style.display = 'inline-block';
                currentScale = 1.0; 
                renderPage(activePageNum);
                buildAccordion(); 
            }).catch(function(err) {
                loader.style.display = 'none';
                console.error("PDF Load Error", err);
            });
        }

        function renderPage(num) {
            pageIsRendering = true;
            loader.style.display = 'block';
            
            pdfDoc.getPage(num).then(function(page) {
                var containerWidth = readerArea.clientWidth - 40;
                var baseFitScale = containerWidth / page.getViewport({ scale: 1.0 }).width;
                
                var finalScale = baseFitScale * currentScale;
                var viewport = page.getViewport({ scale: finalScale });

                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvasWrap.style.width = viewport.width + 'px';
                canvasWrap.style.height = viewport.height + 'px';

                var renderCtx = { canvasContext: ctx, viewport: viewport };
                page.render(renderCtx).promise.then(function() {
                    pageIsRendering = false;
                    loader.style.display = 'none';
                    if (pageNumPending !== null) { renderPage(pageNumPending); pageNumPending = null; }
                });

                if (document.activeElement !== pageInput) {
                    pageInput.value = num;
                }
                pageTotal.innerText = ' / ' + pdfDoc.numPages;
                
                highlightChapter(num);
                saveProgress(activeDocIndex, num);
                
                if (pageNumPending === null) { readerArea.scrollTop = 0; }
            });
        }

        function queueRenderPage(num) { if (pageIsRendering) { pageNumPending = num; } else { renderPage(num); } }
        function onPrevPage() { if (activePageNum <= 1) return; activePageNum--; queueRenderPage(activePageNum); }
        function onNextPage() { if (activePageNum >= pdfDoc.numPages) return; activePageNum++; queueRenderPage(activePageNum); }

        prevBtn.addEventListener('click', onPrevPage);
        nextBtn.addEventListener('click', onNextPage);
        
        function processManualJump() {
            var target = parseInt(pageInput.value);
            if (target >= 1 && target <= pdfDoc.numPages) {
                activePageNum = target;
                queueRenderPage(activePageNum);
            } else {
                pageInput.value = activePageNum; 
            }
            pageInput.blur(); 
        }

        pageInput.addEventListener('change', processManualJump);
        
        pageInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                processManualJump();
            }
        });

        pageInput.addEventListener('touchstart', function(e) { e.stopPropagation(); }, {passive: true});
        pageInput.addEventListener('touchend', function(e) { e.stopPropagation(); }, {passive: true});
        pageInput.addEventListener('click', function(e) { this.focus(); });

        function highlightChapter(currentPg) {
            var list = document.getElementById('chap-list-' + uid + '-' + activeDocIndex);
            if(!list) return;
            var items = list.querySelectorAll('.cppm-eb-chapter-item');
            items.forEach(i => i.classList.remove('active'));
            var activeItem = null;
            items.forEach(function(item) {
                if (parseInt(item.getAttribute('data-page')) <= currentPg) { activeItem = item; }
            });
            if(activeItem) activeItem.classList.add('active');
        }

        function saveProgress(dIdx, page) {
            progressData[dIdx] = page; 
            var fd = new FormData();
            fd.append('action', 'cppm_save_multi_ebook_progress');
            fd.append('ebook_id', eid);
            fd.append('progress_json', JSON.stringify(progressData));
            fetch(ajaxUrl, { method: "POST", body: fd });
        }

        accordion.addEventListener('click', function(e) {
            var docHeader = e.target.closest('.cppm-eb-doc-header');
            if (docHeader) {
                var clickedDIdx = parseInt(docHeader.getAttribute('data-didx'));
                if (clickedDIdx !== activeDocIndex) { loadPDF(clickedDIdx); } else { buildAccordion(); } 
            }
            var chapItem = e.target.closest('.cppm-eb-chapter-item');
            if (chapItem) {
                var c_dIdx = parseInt(chapItem.getAttribute('data-didx'));
                var targetPg = parseInt(chapItem.getAttribute('data-page'));
                if (c_dIdx !== activeDocIndex) { loadPDF(c_dIdx, targetPg); } else { activePageNum = targetPg; queueRenderPage(activePageNum); }
                if(window.innerWidth <= 1024) document.getElementById('sidebar-' + uid).classList.remove('open');
            }
        });

        document.getElementById('zoom-in-' + uid).addEventListener('click', function() { 
            if(currentScale < 4.0) { currentScale += 0.3; queueRenderPage(activePageNum); }
        });
        document.getElementById('zoom-out-' + uid).addEventListener('click', function() { 
            if(currentScale > 0.5) { currentScale -= 0.3; queueRenderPage(activePageNum); } 
        });
        
        document.getElementById('dark-btn-' + uid).addEventListener('click', function() {
            var isDark = wrap.classList.toggle('cppm-dark-mode');
            this.innerHTML = isDark ? svgSun : svgMoon;
            localStorage.setItem('cppm_theme', isDark ? 'dark' : 'light');
        });
        
        document.getElementById('fs-btn-' + uid).addEventListener('click', function() {
            if (!document.fullscreenElement) { wrap.requestFullscreen().catch(err => {}); } else { document.exitFullscreen(); }
        });
        
        document.getElementById('menu-btn-' + uid).addEventListener('click', function() { 
            document.getElementById('sidebar-' + uid).classList.toggle('open'); 
        });

        var touchStartX = 0; var touchEndX = 0;
        var initialPinchDist = null;
        var pinchStartScale = 1.0;
        var pinchMultiplier = 1.0;

        readerArea.addEventListener('touchstart', function(e) { 
            if (e.touches.length === 2) {
                e.preventDefault(); 
                initialPinchDist = Math.hypot(e.touches[0].pageX - e.touches[1].pageX, e.touches[0].pageY - e.touches[1].pageY);
                pinchStartScale = currentScale;
            } else if (e.touches.length === 1) {
                touchStartX = e.touches[0].screenX; 
            }
        }, {passive: false});

        readerArea.addEventListener('touchmove', function(e) {
            if (e.touches.length === 2 && initialPinchDist) {
                e.preventDefault(); 
                var currentPinchDist = Math.hypot(e.touches[0].pageX - e.touches[1].pageX, e.touches[0].pageY - e.touches[1].pageY);
                pinchMultiplier = currentPinchDist / initialPinchDist;
                
                canvasWrap.style.transform = 'scale(' + pinchMultiplier + ')';
                canvasWrap.style.transformOrigin = 'top center';
            }
        }, {passive: false});

        readerArea.addEventListener('touchend', function(e) {
            if (initialPinchDist) {
                if (e.touches.length < 2) {
                    currentScale = pinchStartScale * pinchMultiplier;
                    if (currentScale < 0.5) currentScale = 0.5;
                    if (currentScale > 4.0) currentScale = 4.0; 
                    
                    canvasWrap.style.transform = 'none';
                    initialPinchDist = null;
                    pinchMultiplier = 1.0;
                    queueRenderPage(activePageNum);
                }
            } else if (e.changedTouches && e.changedTouches.length === 1 && !initialPinchDist) {
                touchEndX = e.changedTouches[0].screenX;
                var threshold = 80;
                
                if (currentScale <= 1.1) {
                    if (touchEndX < touchStartX - threshold) { onNextPage(); }
                    if (touchEndX > touchStartX + threshold) { onPrevPage(); }
                }
            }
        }, {passive: false});

        loadPDF(activeDocIndex);
    });
    </script>
    <?php
    return ob_get_clean();
}