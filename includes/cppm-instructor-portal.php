<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. FRONTEND INSTRUCTOR PORTAL SHORTCODE
// ==========================================
add_shortcode('cppm_instructor_portal', 'cppm_render_instructor_portal');

function cppm_render_instructor_portal() {
    // ---------------------------------------------------------
    // SECURITY GATEWAY: ONLY ADMINS AND SHOP MANAGERS ALLOWED
    // ---------------------------------------------------------
    if ( ! is_user_logged_in() ) {
        return '<div style="text-align:center; padding: 50px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; margin: 40px auto;">' . 
               '<h3 style="color: #ef4444; margin-top:0;">Access Denied</h3>' . 
               '<p style="color: #64748b;">You must be logged in to view this portal.</p>' . 
               '<a href="' . wp_login_url() . '" style="background:#2874f0; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; margin-top:15px; font-weight:bold;">Log In</a></div>';
    }

    $current_user = wp_get_current_user();
    $allowed_roles = array('administrator', 'shop_manager');
    
    // Check if the user has at least one of the allowed roles
    if ( ! array_intersect( $allowed_roles, (array) $current_user->roles ) ) {
        return '<div style="text-align:center; padding: 50px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; margin: 40px auto;">' . 
               '<h3 style="color: #ef4444; margin-top:0;">Restricted Area</h3>' . 
               '<p style="color: #64748b;">Only approved Instructors and Administrators can access the authoring portal.</p>' .
               '<a href="' . wc_get_page_permalink('myaccount') . '" style="background:#e2e8f0; color:#334155; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; margin-top:15px; font-weight:bold;">Return to My Account</a></div>';
    }

    // ---------------------------------------------------------
    // RENDER THE PORTAL UI
    // ---------------------------------------------------------
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
    
    ob_start();
    ?>
    <style>
        .cppm-portal-wrapper { display: flex; flex-direction: column; gap: 20px; font-family: system-ui, sans-serif; }
        .cppm-portal-nav { display: flex; background: #ffffff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow-x: auto; border: 1px solid #e2e8f0; }
        .cppm-portal-nav a { padding: 16px 24px; color: #64748b; text-decoration: none; font-weight: 600; white-space: nowrap; border-bottom: 3px solid transparent; transition: 0.2s; }
        .cppm-portal-nav a:hover { color: #0f172a; background: #f8fafc; }
        .cppm-portal-nav a.active { color: #2874f0; border-bottom-color: #2874f0; background: #f0f7ff; }
        .cppm-portal-content { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 30px; border: 1px solid #e2e8f0; min-height: 500px; }
        
        /* Format Selection Cards */
        .cppm-format-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .cppm-format-card { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer; transition: 0.3s; background: #f8fafc; }
        .cppm-format-card:hover { border-color: #2874f0; background: #f0f7ff; transform: translateY(-2px); }
        .cppm-format-card h3 { margin: 0 0 10px 0; color: #0f172a; font-size: 20px; }
        .cppm-format-card p { color: #64748b; font-size: 14px; margin: 0; line-height: 1.5; }
        .cppm-format-icon { font-size: 40px; margin-bottom: 15px; }
    </style>

    <div class="cppm-portal-wrapper">
        <div class="cppm-portal-nav">
            <a href="?tab=dashboard" class="<?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
            <a href="?tab=my-books" class="<?php echo $current_tab === 'my-books' ? 'active' : ''; ?>">📚 My Books</a>
            <a href="?tab=create" class="<?php echo $current_tab === 'create' || $current_tab === 'editor' ? 'active' : ''; ?>">✍️ Create New Book</a>
            <a href="?tab=settings" class="<?php echo $current_tab === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a>
        </div>

        <div class="cppm-portal-content">
            <?php 
                switch ($current_tab) {
                    case 'create':
                        cppm_render_book_format_selector();
                        break;
                    case 'editor':
                        cppm_render_web_book_editor();
                        break;
                    case 'my-books':
                        echo '<h2>Manage Your Books</h2><p>List of authored WooCommerce products will appear here.</p>';
                        break;
                    default:
                        echo '<h2>Instructor Dashboard</h2><p>Sales analytics and recent activity will appear here.</p>';
                        break;
                }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 2. THE FORMAT SELECTOR UI
// ==========================================
function cppm_render_book_format_selector() {
    ?>
    <h2 style="margin-top:0; color:#0f172a;">What kind of book are you publishing?</h2>
    <p style="color:#64748b; margin-bottom: 30px;">Choose the format that best fits your content.</p>
    
    <div class="cppm-format-grid">
        <div class="cppm-format-card" onclick="alert('PDF Uploader Engine will open here.')">
            <div class="cppm-format-icon">📄</div>
            <h3>Upload PDF File</h3>
            <p>Best for standard manuals, scanned documents, and static images. Fully secured and watermarked.</p>
        </div>
        <div class="cppm-format-card" onclick="window.location.href='?tab=editor'">
            <div class="cppm-format-icon">🎵</div>
            <h3>Interactive Web-Book</h3>
            <p>Best for music theory. Write directly on our platform with built-in ABCjs music notation and playback.</p>
        </div>
    </div>
    <?php
}

// ==========================================
// 3. THE DYNAMIC WEB-BOOK BUILDER (FIXED PREVIEW & DRUM DEFAULTS)
// ==========================================
function cppm_render_web_book_editor() {
    ?>
    <script src="https://cdn.tiny.cloud/1/nlr0ea4v5uga6echj2arytqu0442bf8j7lc8srpb4dfia0z6/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/abcjs/6.2.2/abcjs-basic-min.js"></script>

    <style>
        .cppm-portal-nav a { text-decoration: none !important; box-shadow: none !important; border-bottom: 3px solid transparent !important; }
        .cppm-portal-nav a.active { border-bottom-color: #2874f0 !important; }
        .cppm-icon-btn { background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: 0.2s; text-decoration: none; }
        .cppm-icon-btn:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
        .cppm-export-wrap { position: relative; display: inline-block; }
        .cppm-export-menu { visibility: hidden; opacity: 0; position: absolute; top: 100%; right: 0; background: #fff; min-width: 150px; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: 0.2s; transform: translateY(10px); z-index: 100005; padding: 5px 0; margin-top: 5px; }
        .cppm-export-wrap:hover .cppm-export-menu { visibility: visible; opacity: 1; transform: translateY(0); }
        .cppm-export-menu a { padding: 10px 15px; color: #334155; text-decoration: none !important; font-size: 14px; font-weight: 600; cursor: pointer; border: none; background: transparent; text-align: left; transition: 0.2s; }
        .cppm-export-menu a:hover { background: #f8fafc; color: #2874f0; }
        .cppm-publish-btn { background: #10b981; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; display: flex; gap: 6px; align-items: center; }
        .cppm-publish-btn:hover { background: #059669; }
        
        .cppm-ruler-container { background: #f8fafc; padding: 15px; border-bottom: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .cppm-ruler-row { display: flex; align-items: center; gap: 10px; }
        .cppm-ruler-slider { flex: 1; cursor: pointer; }
        .cppm-ruler-label { font-size: 12px; font-weight: 700; color: #475569; width: 130px; display:flex; justify-content:space-between; }
        
        .tox-tinymce-aux { z-index: 9999999 !important; }
        .cppm-del-btn { background:none; border:none; color:#ef4444; cursor:pointer; font-size:14px; padding:0 5px; opacity: 0.6; transition: 0.2s; }
        .cppm-del-btn:hover { opacity: 1; transform: scale(1.1); }
    </style>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h2 id="cppm-dynamic-editor-title" style="margin:0; color:#0f172a; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Untitled Book</h2>
        
        <div style="display:flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <button class="cppm-icon-btn" title="Fullscreen" onclick="togglePortalFullscreen()">⛶</button>
            <label class="cppm-icon-btn" title="Import .docx" style="margin:0;">
                📄 <input type="file" id="cppm-docx-upload" accept=".docx" style="display:none;" onchange="importDocx(event)">
            </label>
            <div class="cppm-export-wrap">
                <button class="cppm-icon-btn" title="Export Options">⬇️</button>
                <div class="cppm-export-menu">
                    <a onclick="exportDocx()">📄 Word (.doc)</a>
                    <a onclick="exportPdf()">📕 PDF Document</a>
                </div>
            </div>
            
            <button class="cppm-icon-btn" title="Preview Current Page" onclick="previewCurrentPage()" style="background:#e0f2fe; border-color:#bae6fd; color:#0369a1;">👁️</button>
            
            <button class="cppm-icon-btn" title="Save Draft (Ctrl+S)" onclick="saveBook('draft')">💾</button>
            <button class="cppm-publish-btn" onclick="openPublishModal()">🚀 Publish</button>
        </div>
    </div>

    <input type="text" id="cppm-book-title" placeholder="Enter Book Title..." oninput="document.getElementById('cppm-dynamic-editor-title').innerText = this.value || 'Untitled Book';" style="width:100%; padding:15px; border:1px solid #cbd5e1; border-radius:8px; font-size:18px; font-weight:bold; margin-bottom:20px; outline:none; background:#f8fafc;">
    
    <div style="display: flex; gap: 20px; height: 750px; position: relative;" id="cppm-builder-layout">
        
        <div style="width: 280px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0;">
            <div style="padding: 15px; background: #e2e8f0; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
                <strong style="color: #0f172a;">Table of Contents</strong>
                <button onclick="addChapter()" style="background: #2874f0; color: #fff; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 12px; font-weight:bold;">+ Chapter</button>
            </div>
            <div id="cppm-toc-list" style="flex: 1; overflow-y: auto; padding: 10px;"></div>
        </div>

        <div style="flex: 1; display: flex; flex-direction: column; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; background: #f8fafc;">
            <div id="cppm-current-editing-label" style="padding: 10px 15px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #334155;">
                Select a page to start editing
            </div>

            <div class="cppm-ruler-container">
                <div class="cppm-ruler-row"><div class="cppm-ruler-label"><span>⬆️ Top:</span> <span id="cppm-m-top-val">20</span>mm</div><input type="range" min="0" max="100" step="1" value="20" class="cppm-ruler-slider" id="cppm-m-top" oninput="adjustQuadMargins()"></div>
                <div class="cppm-ruler-row"><div class="cppm-ruler-label"><span>⬇️ Bottom:</span> <span id="cppm-m-bot-val">20</span>mm</div><input type="range" min="0" max="100" step="1" value="20" class="cppm-ruler-slider" id="cppm-m-bot" oninput="adjustQuadMargins()"></div>
                <div class="cppm-ruler-row"><div class="cppm-ruler-label"><span>⬅️ Left:</span> <span id="cppm-m-left-val">20</span>mm</div><input type="range" min="0" max="100" step="1" value="20" class="cppm-ruler-slider" id="cppm-m-left" oninput="adjustQuadMargins()"></div>
                <div class="cppm-ruler-row"><div class="cppm-ruler-label"><span>➡️ Right:</span> <span id="cppm-m-right-val">20</span>mm</div><input type="range" min="0" max="100" step="1" value="20" class="cppm-ruler-slider" id="cppm-m-right" oninput="adjustQuadMargins()"></div>
            </div>

            <div style="flex: 1; overflow: hidden; position: relative;" id="cppm-editor-wrapper">
                <textarea id="cppm-tinymce-editor" style="height: 100%;"></textarea>
            </div>
        </div>
    </div>

    <div id="cppm-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.9); z-index:10000000; align-items:flex-start; justify-content:center; overflow-y:auto; padding: 40px 20px;">
        <div style="position:relative; width: 210mm; min-height: 297mm; background: #ffffff; box-shadow: 0 10px 40px rgba(0,0,0,0.5); padding: 0; margin: 0 auto 40px auto; border-radius: 4px;">
            <button onclick="document.getElementById('cppm-preview-modal').style.display='none'" style="position:absolute; top: -45px; right: 0; background: #ef4444; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size:14px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">❌ Close Preview</button>
            <div id="cppm-preview-content-zone" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #0f172a; box-sizing: border-box; width: 100%; min-height: 100%;"></div>
        </div>
    </div>

    <div id="cppm-pdf-render-zone" style="position:absolute; left:-9999px; top:0; width:800px; background:#fff; padding:40px;"></div>

    <script>
        function togglePortalFullscreen() {
            let portalContent = document.querySelector('.cppm-portal-content');
            if (!portalContent) return;
            if (!document.fullscreenElement) {
                portalContent.requestFullscreen().catch(err => { alert(`Error: ${err.message}`); });
                portalContent.style.background = "#fff";
                portalContent.style.overflowY = "auto";
            } else { document.exitFullscreen(); }
        }

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveBook('draft'); }
        });

        let bookData = [
            {
                id: 'chap_1', title: 'Chapter 1',
                pages: [ { id: 'page_1_1', title: 'Page 1', content: '<p>Start writing here...</p>', mTop: 20, mBot: 20, mLeft: 20, mRight: 20 } ]
            }
        ];
        let currentPageId = 'page_1_1';
        let currentChapterId = 'chap_1';

        function adjustQuadMargins() {
            let mt = document.getElementById('cppm-m-top').value; let mb = document.getElementById('cppm-m-bot').value;
            let ml = document.getElementById('cppm-m-left').value; let mr = document.getElementById('cppm-m-right').value;
            
            document.getElementById('cppm-m-top-val').innerText = mt; document.getElementById('cppm-m-bot-val').innerText = mb;
            document.getElementById('cppm-m-left-val').innerText = ml; document.getElementById('cppm-m-right-val').innerText = mr;
            
            let ed = tinymce.get('cppm-tinymce-editor');
            if(ed && ed.getBody()) {
                ed.dom.setStyle(ed.getBody(), 'padding', `${mt}mm ${mr}mm ${mb}mm ${ml}mm`);
                for(let c of bookData) { for(let p of c.pages) { if(p.id === currentPageId) { p.mTop = mt; p.mBot = mb; p.mLeft = ml; p.mRight = mr; } } }
            }
        }

// --- FIXED: RENDER AFTER MODAL OPENS TO FORCE WIDTH STRETCH ---
function previewCurrentPage() {
    saveEditorToCurrentPage();
    
    let content = tinymce.get('cppm-tinymce-editor').getContent();
    let previewZone = document.getElementById('cppm-preview-content-zone');
    
    let mt = document.getElementById('cppm-m-top').value || 20; 
    let mb = document.getElementById('cppm-m-bot').value || 20;
    let ml = document.getElementById('cppm-m-left').value || 20; 
    let mr = document.getElementById('cppm-m-right').value || 20;
    
    previewZone.style.padding = `${mt}mm ${mr}mm ${mb}mm ${ml}mm`;
    previewZone.innerHTML = content;
    
    // CRITICAL FIX 1: Open the modal FIRST so the container has a physical width > 0
    document.getElementById('cppm-preview-modal').style.display = 'flex';
    
    // CRITICAL FIX 2: Wait 50ms for the browser to paint the full A4 page, THEN render ABC
    setTimeout(() => {
        let musicBlocks = previewZone.querySelectorAll('.music-notation-block');
        musicBlocks.forEach((block, index) => {
            
            let cleanAbcText = block.innerText || block.textContent;
            cleanAbcText = cleanAbcText.replace(/(\r\n|\n|\r)+/g, '\n').trim();
            
            let uniqueId = 'cppm-preview-music-' + index;
            let newContainer = document.createElement('div');
            newContainer.id = uniqueId;
            newContainer.style.width = '100%'; // Reaches 100% of the newly visible A4 page
            newContainer.style.margin = '20px 0';
            
            block.parentNode.replaceChild(newContainer, block);
            
            // Render with explicit stretch commands now that width is known
            ABCJS.renderAbc(uniqueId, cleanAbcText, { 
                add_classes: true,
                responsive: "resize",
                format: { 
                    stretchlast: true 
                }
            });
        });
    }, 50);
}

        tinymce.init({
            selector: '#cppm-tinymce-editor',
            ui_container: document.querySelector('.cppm-portal-content') ? '.cppm-portal-content' : 'body',
            plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
            menubar: 'file edit view insert format tools table help',
            toolbar: 'undo redo | bold italic underline strikethrough | fontfamily fontsize blocks | alignleft aligncenter alignright | numlist bullist | forecolor backcolor | table image media | custom_music_button',
            toolbar_sticky: true,
            height: '100%',
            
            content_style: `
                html { background: #e2e8f0; padding: 20px 0; overflow-y: scroll; }
                body { 
                    font-family: Helvetica, Arial, sans-serif; font-size: 16px; background: #ffffff;
                    width: 210mm; min-height: 297mm; margin: 0 auto; 
                    box-shadow: 0 4px 15px rgba(0,0,0,0.08); box-sizing: border-box; overflow: hidden;
                    padding: 20mm 20mm 20mm 20mm; 
                } 
                .music-notation-block { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; font-family: monospace; color: #92400e; display: block; margin: 10px 0; white-space: pre-wrap; }
                p { margin-top: 0; margin-bottom: 1rem; }
            `,
            
            setup: function (editor) {
                editor.on('init', function() {
                    renderToC();
                    loadPageIntoEditor(currentPageId);
                });

                editor.on('keydown', function(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveBook('draft'); }
                });

                editor.on('blur change', function() {
                    if (currentPageId) { saveEditorToCurrentPage(); }
                });

                // --- FIXED: DEFAULT NOTATION MATCHES IMAGE ---
                editor.ui.registry.addButton('custom_music_button', {
                    text: '🎵 Insert Music',
                    onAction: function (_) {
                        const drumNotation = "X: 1\nT: ORDERLY SERGEANTS\nM: 6/8\nL: 1/8\nV: 1 clef=none stem=up\nK: C\n%%stems up\n%%staffwidth 680\n%%stretchlast 1\n\"^J=108\"B2 B B3 | B3 B3 | B2 B B3 | B3 z3 |]\nw: Or-der-ly | Ser-geants, | fall in, fall | in!";
                        editor.insertContent('<pre class="music-notation-block">' + drumNotation + '</pre><p><br></p>');
                    }
                });
            }
        });

        // --- TOC, ROUTING & DELETION LOGIC (Unchanged from previous) ---
        function renderToC() {
            const tocList = document.getElementById('cppm-toc-list');
            tocList.innerHTML = '';
            bookData.forEach((chapter) => {
                let chapDiv = document.createElement('div'); chapDiv.style.marginBottom = '15px';
                chapDiv.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center; background:#f1f5f9; padding:8px; border-radius:4px; margin-bottom:5px;"><strong style="font-size:14px; color:#0f172a; flex:1;">${chapter.title}</strong><div><button onclick="addPage('${chapter.id}')" style="background:none; border:none; color:#2874f0; cursor:pointer; font-size:12px; font-weight:bold;">+ Page</button><button class="cppm-del-btn" onclick="deleteChapter('${chapter.id}')">🗑️</button></div></div>`;
                
                chapter.pages.forEach((page) => {
                    let isActive = (page.id === currentPageId);
                    let pageDiv = document.createElement('div');
                    pageDiv.style.cssText = `display:flex; justify-content:space-between; align-items:center; padding:6px 10px 6px 20px; background:${isActive ? '#e0f2fe' : 'transparent'}; border-radius:4px; margin-bottom:2px;`;
                    pageDiv.innerHTML = `<div onclick="switchPage('${page.id}', '${chapter.id}')" style="cursor:pointer; font-size:13px; color: ${isActive ? '#2874f0' : '#475569'}; font-weight: ${isActive ? 'bold' : '500'}; flex:1;">📄 ${page.title}</div><button class="cppm-del-btn" onclick="deletePage('${chapter.id}', '${page.id}')">🗑️</button>`;
                    chapDiv.appendChild(pageDiv);
                });
                tocList.appendChild(chapDiv);
            });
        }

        function deleteChapter(chapId) {
            if(confirm("Are you sure you want to delete this entire chapter and all its pages?")) {
                bookData = bookData.filter(c => c.id !== chapId);
                if(currentChapterId === chapId) {
                    currentPageId = null;
                    if(bookData.length > 0 && bookData[0].pages.length > 0) { switchPage(bookData[0].pages[0].id, bookData[0].id); } 
                    else { tinymce.get('cppm-tinymce-editor').setContent(''); document.getElementById('cppm-current-editing-label').innerText = "No pages available."; }
                }
                renderToC();
            }
        }

        function deletePage(chapId, pageId) {
            if(confirm("Delete this page?")) {
                let chapter = bookData.find(c => c.id === chapId);
                if(chapter) {
                    chapter.pages = chapter.pages.filter(p => p.id !== pageId);
                    if(currentPageId === pageId) {
                        currentPageId = null;
                        if(chapter.pages.length > 0) { switchPage(chapter.pages[chapter.pages.length - 1].id, chapId); } 
                        else { tinymce.get('cppm-tinymce-editor').setContent(''); document.getElementById('cppm-current-editing-label').innerText = `Editing: ${chapter.title} > No Pages`; }
                    }
                    renderToC();
                }
            }
        }

        function saveEditorToCurrentPage() {
            if(!currentPageId) return;
            let content = tinymce.get('cppm-tinymce-editor').getContent();
            for(let c of bookData) { for(let p of c.pages) { if(p.id === currentPageId) { p.content = content; return; } } }
        }

        function switchPage(pageId, chapId = null) {
            if(currentPageId) saveEditorToCurrentPage();
            currentPageId = pageId; if(chapId) currentChapterId = chapId;
            loadPageIntoEditor(pageId); renderToC();
        }

        function loadPageIntoEditor(pageId) {
            for(let c of bookData) {
                for(let p of c.pages) {
                    if(p.id === pageId) {
                        document.getElementById('cppm-current-editing-label').innerText = `Editing: ${c.title} > ${p.title}`;
                        let mt = p.mTop !== undefined ? p.mTop : 20; let mb = p.mBot !== undefined ? p.mBot : 20;
                        let ml = p.mLeft !== undefined ? p.mLeft : 20; let mr = p.mRight !== undefined ? p.mRight : 20;
                        
                        document.getElementById('cppm-m-top').value = mt; document.getElementById('cppm-m-bot').value = mb;
                        document.getElementById('cppm-m-left').value = ml; document.getElementById('cppm-m-right').value = mr;
                        document.getElementById('cppm-m-top-val').innerText = mt; document.getElementById('cppm-m-bot-val').innerText = mb;
                        document.getElementById('cppm-m-left-val').innerText = ml; document.getElementById('cppm-m-right-val').innerText = mr;
                        
                        let ed = tinymce.get('cppm-tinymce-editor');
                        if(ed && ed.getBody()) { ed.dom.setStyle(ed.getBody(), 'padding', `${mt}mm ${mr}mm ${mb}mm ${ml}mm`); }
                        ed.setContent(p.content);
                        return;
                    }
                }
            }
        }

        function addChapter() {
            let chapTitle = prompt("Enter Chapter Title:"); if(!chapTitle) return;
            bookData.push({ id: 'chap_' + Date.now(), title: chapTitle, pages: [] }); renderToC();
        }

        function addPage(chapterId) {
            let pageNum = 1;
            for(let c of bookData) { if(c.id === chapterId) { pageNum = c.pages.length + 1; break; } }
            let newPageId = 'page_' + Date.now();
            let mt = document.getElementById('cppm-m-top').value || 20; let mb = document.getElementById('cppm-m-bot').value || 20;
            let ml = document.getElementById('cppm-m-left').value || 20; let mr = document.getElementById('cppm-m-right').value || 20;
            
            for(let c of bookData) {
                if(c.id === chapterId) {
                    c.pages.push({ id: newPageId, title: 'Page ' + pageNum, content: '<p></p>', mTop: mt, mBot: mb, mLeft: ml, mRight: mr });
                    switchPage(newPageId, chapterId); break;
                }
            }
        }

        function showToast(msg) {
            let toast = document.createElement('div'); toast.innerText = msg;
            toast.style.cssText = "position:fixed; bottom:20px; right:20px; background:#10b981; color:#fff; padding:12px 24px; border-radius:8px; font-weight:bold; z-index:999999; box-shadow:0 4px 10px rgba(0,0,0,0.1);";
            document.body.appendChild(toast); setTimeout(() => toast.remove(), 2500);
        }

        function saveBook(status) { saveEditorToCurrentPage(); showToast("✅ Book saved!"); console.log(JSON.stringify(bookData)); }
        function openPublishModal() { alert("Ready to Publish!"); }
        function exportDocx() { alert("Exporting .docx..."); }
        function exportPdf() { alert("Exporting .pdf..."); }
    </script>
    <?php
}