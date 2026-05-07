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
    if ( ! is_user_logged_in() ) {
        return '<div style="text-align:center; padding: 50px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; margin: 40px auto;">' . 
               '<h3 style="color: #ef4444; margin-top:0;">Access Denied</h3>' . 
               '<p style="color: #64748b;">You must be logged in to view this portal.</p>' . 
               '<a href="' . wp_login_url() . '" style="background:#2874f0; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; margin-top:15px; font-weight:bold;">Log In</a></div>';
    }

    $current_user = wp_get_current_user();
    $allowed_roles = array('administrator', 'shop_manager');
    
    if ( ! array_intersect( $allowed_roles, (array) $current_user->roles ) ) {
        return '<div style="text-align:center; padding: 50px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; margin: 40px auto;">' . 
               '<h3 style="color: #ef4444; margin-top:0;">Restricted Area</h3>' . 
               '<p style="color: #64748b;">Only approved Instructors and Administrators can access the authoring portal.</p>' .
               '<a href="' . wc_get_page_permalink('myaccount') . '" style="background:#e2e8f0; color:#334155; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; margin-top:15px; font-weight:bold;">Return to My Account</a></div>';
    }

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
        
        .cppm-format-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .cppm-format-card { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer; transition: 0.3s; background: #f8fafc; }
        .cppm-format-card:hover { border-color: #2874f0; background: #f0f7ff; transform: translateY(-2px); }
        .cppm-format-card h3 { margin: 0 0 10px 0; color: #0f172a; font-size: 20px; }
        .cppm-format-card p { color: #64748b; font-size: 14px; margin: 0; line-height: 1.5; }
        .cppm-format-icon { font-size: 40px; margin-bottom: 15px; }

        .cppm-btn-primary { background: #2874f0; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; text-decoration: none; display: inline-block;}
        .cppm-btn-primary:hover { background: #1a5ac6; color: #fff; }
        .cppm-btn-success { background: #10b981; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .cppm-btn-success:hover { background: #059669; }
        .cppm-btn-outline { background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; text-decoration:none; display:inline-block; }
        .cppm-btn-outline:hover { background: #f1f5f9; color: #0f172a; }
        
        .cppm-form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; background: #f8fafc; margin-bottom: 15px; }
        .cppm-form-input:focus { border-color: #2874f0; background: #fff; }
    </style>

    <script>
        window.cppm_ajaxurl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
        window.cppm_mock_nonce = "<?php echo wp_create_nonce('cppm_mock_save_action'); ?>";
    </script>

    <div class="cppm-portal-wrapper">
        <div class="cppm-portal-nav">
            <a href="?tab=dashboard" class="<?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
            <a href="?tab=my-books" class="<?php echo $current_tab === 'my-books' ? 'active' : ''; ?>">📚 Books</a>
            <a href="?tab=create" class="<?php echo $current_tab === 'create' || $current_tab === 'editor' ? 'active' : ''; ?>">✍️ Create Book</a>
            <a href="?tab=mock-tests" class="<?php echo $current_tab === 'mock-tests' || $current_tab === 'create-mock' ? 'active' : ''; ?>">🎯 Mock Tests</a>
            <a href="?tab=settings" class="<?php echo $current_tab === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a>
        </div>

        <div class="cppm-portal-content">
            <?php 
                switch ($current_tab) {
                    case 'create':
                        cppm_render_book_format_selector(); break;
                    case 'editor':
                        cppm_render_web_book_editor(); break;
                    case 'my-books':
                        echo '<h2>Manage Your Books</h2><p>List of authored WooCommerce products will appear here.</p>'; break;
                    case 'mock-tests':
                        cppm_render_mock_dashboard(); break;
                    case 'create-mock':
                        cppm_render_mock_test_editor(); break;
                    default:
                        echo '<h2>Instructor Dashboard</h2><p>Sales analytics and recent activity will appear here.</p>'; break;
                }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 2. BOOK FORMAT SELECTOR
// ==========================================
function cppm_render_book_format_selector() {
    ?>
    <h2 style="margin-top:0; color:#0f172a;">What kind of book are you publishing?</h2>
    <div class="cppm-format-grid">
        <div class="cppm-format-card" onclick="alert('PDF Uploader Engine will open here.')">
            <div class="cppm-format-icon">📄</div><h3>Upload PDF File</h3>
            <p>Best for standard manuals, scanned documents, and static images. Fully secured and watermarked.</p>
        </div>
        <div class="cppm-format-card" onclick="window.location.href='?tab=editor'">
            <div class="cppm-format-icon">🎵</div><h3>Interactive Web-Book</h3>
            <p>Best for music theory. Write directly on our platform with built-in ABCjs music notation and playback.</p>
        </div>
    </div>
    <?php
}

// ==========================================
// 3. THE DYNAMIC WEB-BOOK BUILDER
// ==========================================
function cppm_render_web_book_editor() {
    ?>
    <script src="https://cdn.tiny.cloud/1/nlr0ea4v5uga6echj2arytqu0442bf8j7lc8srpb4dfia0z6/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/abcjs/6.2.2/abcjs-basic-min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/abcjs/6.2.2/abcjs-audio.min.css">

    <style>
        .cppm-icon-btn { background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: 0.2s; text-decoration: none; }
        .cppm-icon-btn:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
        .cppm-export-wrap { position: relative; display: inline-block; }
        .cppm-export-menu { visibility: hidden; opacity: 0; position: absolute; top: 100%; right: 0; background: #fff; min-width: 150px; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: 0.2s; transform: translateY(10px); z-index: 100005; padding: 5px 0; margin-top: 5px; }
        .cppm-export-wrap:hover .cppm-export-menu { visibility: visible; opacity: 1; transform: translateY(0); }
        .cppm-export-menu a { padding: 10px 15px; color: #334155; text-decoration: none !important; font-size: 14px; font-weight: 600; cursor: pointer; border: none; background: transparent; text-align: left; transition: 0.2s; }
        .cppm-export-menu a:hover { background: #f8fafc; color: #2874f0; }
        
        .cppm-ruler-container { background: #f8fafc; padding: 15px; border-bottom: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .cppm-ruler-row { display: flex; align-items: center; gap: 10px; }
        .cppm-ruler-slider { flex: 1; cursor: pointer; }
        .cppm-ruler-label { font-size: 12px; font-weight: 700; color: #475569; width: 130px; display:flex; justify-content:space-between; }
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
            <button class="cppm-btn-success" onclick="openPublishModal()">🚀 Publish</button>
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

    <div id="cppm-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter: blur(8px); z-index:10000000; flex-direction: column; align-items:center; overflow-y:auto;">
        <div style="position: sticky; top: 0; width: 100%; background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); z-index: 10;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="background: #2874f0; color: #fff; padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 12px;">PREVIEW MODE</span>
                <span style="color: #64748b; font-size: 16px; border-left: 2px solid #e2e8f0; padding-left: 15px; font-weight: 600;" id="cppm-preview-header-title">Loading...</span>
            </div>
            <button onclick="document.getElementById('cppm-preview-modal').style.display='none'" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size:14px; transition: 0.2s;">Exit Preview ✕</button>
        </div>

        <div style="position:relative; width: 210mm; min-height: 297mm; background: #ffffff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #cbd5e1; padding: 0; margin: 40px auto 80px auto; border-radius: 4px; overflow: hidden;">
            <div id="cppm-preview-content-zone" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #0f172a; box-sizing: border-box; width: 100%; min-height: 100%;"></div>
        </div>
    </div>

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

        let bookData = [
            { id: 'chap_1', title: 'Chapter 1', pages: [ { id: 'page_1_1', title: 'Page 1', content: '<p>Start writing here...</p>', mTop: 20, mBot: 20, mLeft: 20, mRight: 20 } ] }
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

        function previewCurrentPage() {
            saveEditorToCurrentPage();
            document.getElementById('cppm-preview-header-title').innerText = document.getElementById('cppm-book-title').value || 'Untitled Book';
            let content = tinymce.get('cppm-tinymce-editor').getContent();
            let previewZone = document.getElementById('cppm-preview-content-zone');
            
            let mt = document.getElementById('cppm-m-top').value || 20; let mb = document.getElementById('cppm-m-bot').value || 20;
            let ml = document.getElementById('cppm-m-left').value || 20; let mr = document.getElementById('cppm-m-right').value || 20;
            
            previewZone.style.padding = `${mt}mm ${mr}mm ${mb}mm ${ml}mm`;
            previewZone.innerHTML = content;
            document.getElementById('cppm-preview-modal').style.display = 'flex';
            
            setTimeout(() => {
                let musicBlocks = previewZone.querySelectorAll('.music-notation-block');
                musicBlocks.forEach((block, index) => {
                    let cleanAbcText = block.innerText || block.textContent;
                    cleanAbcText = cleanAbcText.replace(/(\r\n|\n|\r)+/g, '\n').trim();
                    
                    let wrapperContainer = document.createElement('div');
                    wrapperContainer.style.width = '100%'; wrapperContainer.style.margin = '20px 0 40px 0';
                    
                    let audioId = 'cppm-preview-audio-' + index;
                    let audioContainer = document.createElement('div');
                    audioContainer.id = audioId; audioContainer.style.background = '#f8fafc'; audioContainer.style.border = '1px solid #e2e8f0'; audioContainer.style.borderRadius = '8px'; audioContainer.style.padding = '8px 15px'; audioContainer.style.marginBottom = '25px';
                    wrapperContainer.appendChild(audioContainer);

                    let visualId = 'cppm-preview-music-' + index;
                    let visualContainer = document.createElement('div');
                    visualContainer.id = visualId;
                    wrapperContainer.appendChild(visualContainer);
                    
                    block.parentNode.replaceChild(wrapperContainer, block);
                    
                    let visualObj = ABCJS.renderAbc(visualId, cleanAbcText, { add_classes: true, staffwidth: 650, wrap: { minSpacing: 3.5, maxSpacing: 5.5 }, format: { stretchlast: true } });
                    
                    if (ABCJS.synth.supportsAudio()) {
                        let synthControl = new ABCJS.synth.SynthController();
                        synthControl.load("#" + audioId, null, { displayLoop: true, displayRestart: true, displayPlay: true, displayProgress: true, displayWarp: true });
                        let midiBuffer = new ABCJS.synth.CreateSynth();
                        midiBuffer.init({ visualObj: visualObj[0], options: { soundFontUrl: "https://paulrosen.github.io/midi-js-soundfonts/FluidR3_GM/" } })
                        .then(function () { synthControl.setTune(visualObj[0], false, { chordsOff: true }); })
                        .catch(function (error) { console.warn("Audio failed to load:", error); });
                    }
                });
            }, 100); 
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
                body { font-family: Helvetica, Arial, sans-serif; font-size: 16px; background: #ffffff; width: 210mm; min-height: 297mm; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.08); box-sizing: border-box; overflow: hidden; padding: 20mm 20mm 20mm 20mm; } 
                .music-notation-block { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; font-family: monospace; color: #92400e; display: block; margin: 10px 0; white-space: pre-wrap; }
                p { margin-top: 0; margin-bottom: 1rem; }
            `,
            setup: function (editor) {
                editor.on('init', function() { renderToC(); loadPageIntoEditor(currentPageId); });
                editor.on('keydown', function(e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveBook('draft'); } });
                editor.on('blur change', function() { if (currentPageId) { saveEditorToCurrentPage(); } });
                editor.ui.registry.addButton('custom_music_button', {
                    text: '🎵 Insert Music',
                    onAction: function (_) {
                        const drumNotation = "X: 1\nT: ORDERLY SERGEANTS\nM: 6/8\nL: 1/8\nV: 1 clef=none stem=up\nK: C\n%%stems up\n%%staffwidth 680\n%%stretchlast 1\n\"^J=108\"B2 B B3 | B3 B3 | B2 B B3 | B3 z3 |]\nw: Or-der-ly | Ser-geants, | fall in, fall | in!";
                        editor.insertContent('<pre class="music-notation-block">' + drumNotation + '</pre><p><br></p>');
                    }
                });
            }
        });

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
        function deleteChapter(chapId) { if(confirm("Are you sure you want to delete this entire chapter and all its pages?")) { bookData = bookData.filter(c => c.id !== chapId); if(currentChapterId === chapId) { currentPageId = null; if(bookData.length > 0 && bookData[0].pages.length > 0) { switchPage(bookData[0].pages[0].id, bookData[0].id); } else { tinymce.get('cppm-tinymce-editor').setContent(''); document.getElementById('cppm-current-editing-label').innerText = "No pages available."; } } renderToC(); } }
        function deletePage(chapId, pageId) { if(confirm("Delete this page?")) { let chapter = bookData.find(c => c.id === chapId); if(chapter) { chapter.pages = chapter.pages.filter(p => p.id !== pageId); if(currentPageId === pageId) { currentPageId = null; if(chapter.pages.length > 0) { switchPage(chapter.pages[chapter.pages.length - 1].id, chapId); } else { tinymce.get('cppm-tinymce-editor').setContent(''); document.getElementById('cppm-current-editing-label').innerText = `Editing: ${chapter.title} > No Pages`; } } renderToC(); } } }
        function saveEditorToCurrentPage() { if(!currentPageId) return; let content = tinymce.get('cppm-tinymce-editor').getContent(); for(let c of bookData) { for(let p of c.pages) { if(p.id === currentPageId) { p.content = content; return; } } } }
        function switchPage(pageId, chapId = null) { if(currentPageId) saveEditorToCurrentPage(); currentPageId = pageId; if(chapId) currentChapterId = chapId; loadPageIntoEditor(pageId); renderToC(); }
        function loadPageIntoEditor(pageId) { for(let c of bookData) { for(let p of c.pages) { if(p.id === pageId) { document.getElementById('cppm-current-editing-label').innerText = `Editing: ${c.title} > ${p.title}`; let mt = p.mTop !== undefined ? p.mTop : 20; let mb = p.mBot !== undefined ? p.mBot : 20; let ml = p.mLeft !== undefined ? p.mLeft : 20; let mr = p.mRight !== undefined ? p.mRight : 20; document.getElementById('cppm-m-top').value = mt; document.getElementById('cppm-m-bot').value = mb; document.getElementById('cppm-m-left').value = ml; document.getElementById('cppm-m-right').value = mr; document.getElementById('cppm-m-top-val').innerText = mt; document.getElementById('cppm-m-bot-val').innerText = mb; document.getElementById('cppm-m-left-val').innerText = ml; document.getElementById('cppm-m-right-val').innerText = mr; let ed = tinymce.get('cppm-tinymce-editor'); if(ed && ed.getBody()) { ed.dom.setStyle(ed.getBody(), 'padding', `${mt}mm ${mr}mm ${mb}mm ${ml}mm`); } ed.setContent(p.content); return; } } } }
        function addChapter() { let chapTitle = prompt("Enter Chapter Title:"); if(!chapTitle) return; bookData.push({ id: 'chap_' + Date.now(), title: chapTitle, pages: [] }); renderToC(); }
        function addPage(chapterId) { let pageNum = 1; for(let c of bookData) { if(c.id === chapterId) { pageNum = c.pages.length + 1; break; } } let newPageId = 'page_' + Date.now(); let mt = document.getElementById('cppm-m-top').value || 20; let mb = document.getElementById('cppm-m-bot').value || 20; let ml = document.getElementById('cppm-m-left').value || 20; let mr = document.getElementById('cppm-m-right').value || 20; for(let c of bookData) { if(c.id === chapterId) { c.pages.push({ id: newPageId, title: 'Page ' + pageNum, content: '<p></p>', mTop: mt, mBot: mb, mLeft: ml, mRight: mr }); switchPage(newPageId, chapterId); break; } } }
        function showToast(msg) { let toast = document.createElement('div'); toast.innerText = msg; toast.style.cssText = "position:fixed; bottom:20px; right:20px; background:#10b981; color:#fff; padding:12px 24px; border-radius:8px; font-weight:bold; z-index:999999; box-shadow:0 4px 10px rgba(0,0,0,0.1);"; document.body.appendChild(toast); setTimeout(() => toast.remove(), 2500); }
        function saveBook(status) { saveEditorToCurrentPage(); showToast("✅ Book saved!"); console.log(JSON.stringify(bookData)); }
        function openPublishModal() { alert("Ready to Publish!"); }
        function exportDocx() { alert("Exporting .docx..."); }
        function exportPdf() { alert("Exporting .pdf..."); }
    </script>
    <?php
}

// ==========================================
// 4. MOCK TEST DASHBOARD (THE LIST VIEW)
// ==========================================
function cppm_render_mock_dashboard() {
    $current_user_id = get_current_user_id();

    // Query tests created by this instructor
    $args = array(
        'post_type'      => 'cppm_mock_test',
        'post_status'    => array('publish', 'draft'),
        'author'         => $current_user_id,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC'
    );
    $tests_query = new WP_Query($args);
    ?>
    <style>
        .cppm-test-list { width:100%; border-collapse:collapse; margin-top:20px; }
        .cppm-test-list th { text-align:left; padding:15px; background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; font-size:14px; }
        .cppm-test-list td { padding:15px; border-bottom:1px solid #e2e8f0; vertical-align:middle; color:#0f172a; }
        .cppm-test-list tr:hover { background:#f8fafc; }
        .cppm-badge-publish { background:#dcfce7; color:#166534; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:bold; }
        .cppm-badge-draft { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:bold; }
    </style>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
        <div>
            <h2 style="margin:0; color:#0f172a;">Mock Test Engine</h2>
            <p style="color:#64748b; margin:5px 0 0;">Manage your high-fidelity practice exams.</p>
        </div>
        <a href="?tab=create-mock" class="cppm-btn-primary">+ Create New Mock Test</a>
    </div>

    <?php if ( $tests_query->have_posts() ) : ?>
        <table class="cppm-test-list">
            <thead>
                <tr>
                    <th>Test Title</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ( $tests_query->have_posts() ) : $tests_query->the_post(); 
                    $post_id = get_the_ID();
                    $status = get_post_status();
                    $status_class = ($status === 'publish') ? 'cppm-badge-publish' : 'cppm-badge-draft';
                    $status_label = ($status === 'publish') ? 'Published' : 'Draft';
                    $toggle_btn_text = ($status === 'publish') ? 'Hide (Draft)' : 'Publish Test';
                ?>
                <tr>
                    <td style="font-weight:600; font-size:16px;">
                        <?php the_title(); ?><br>
                        <span style="font-size:12px; color:#64748b; font-weight:normal;">ID: <?php echo $post_id; ?></span>
                    </td>
                    <td><span id="status-badge-<?php echo $post_id; ?>" class="<?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                    <td style="color:#64748b; font-size:14px;"><?php echo get_the_date(); ?></td>
                    <td style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                        <a href="?tab=create-mock&edit_id=<?php echo $post_id; ?>" class="cppm-btn-outline" style="padding:6px 12px; font-size:13px;">✏️ Edit</a>
                        <button onclick="toggleMockStatus(<?php echo $post_id; ?>, this)" class="cppm-btn-outline" style="padding:6px 12px; font-size:13px; cursor:pointer;"><?php echo $toggle_btn_text; ?></button>
                    </td>
                </tr>
                <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>
        
        <script>
            function toggleMockStatus(postId, btn) {
                let formData = new URLSearchParams();
                formData.append('action', 'cppm_toggle_mock_status');
                formData.append('security', window.cppm_mock_nonce);
                formData.append('post_id', postId);

                let originalText = btn.innerText;
                btn.innerText = "Updating...";
                btn.style.opacity = '0.5';
                
                fetch(window.cppm_ajaxurl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                })
                .then(res => res.json())
                .then(result => {
                    btn.style.opacity = '1';
                    if(result.success) {
                        btn.innerText = result.data.new_status === 'publish' ? 'Hide (Draft)' : 'Publish Test';
                        let badge = document.getElementById('status-badge-' + postId);
                        if(badge) {
                            badge.innerText = result.data.label;
                            badge.className = result.data.new_status === 'publish' ? 'cppm-badge-publish' : 'cppm-badge-draft';
                        }
                    } else { alert(result.data); btn.innerText = originalText; }
                })
                .catch(err => { alert("Network Error"); btn.innerText = originalText; btn.style.opacity = '1'; });
            }
        </script>
    <?php else : ?>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:40px; text-align:center;">
            <span style="font-size:40px; display:block; margin-bottom:15px;">📝</span>
            <h3 style="margin:0 0 10px;">No mock tests yet</h3>
            <p style="color:#64748b; margin-bottom:20px;">Start building your first TCS iON style exam database.</p>
            <a href="?tab=create-mock" class="cppm-btn-primary">Launch Test Builder</a>
        </div>
    <?php endif;
}

// ==========================================
// 5. MOCK TEST CREATOR (PRE-LOAD DATA INJECTED)
// ==========================================
function cppm_render_mock_test_editor() {
    
    $edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
    $preloaded_title = '';
    $preloaded_b64 = ''; 

    if ( $edit_id > 0 ) {
        $post = get_post( $edit_id );
        if ( $post && ( $post->post_author == get_current_user_id() || current_user_can('manage_options') ) ) {
            $preloaded_title = $post->post_title;
            
            // Check for the new secure Base64 meta
            $b64_meta = get_post_meta( $edit_id, '_cppm_mock_test_b64', true );
            if ( ! empty($b64_meta) ) {
                $preloaded_b64 = $b64_meta;
            } else {
                // Fallback attempt for older tests
                $old_meta = get_post_meta( $edit_id, '_cppm_mock_test_json', true );
                if (!empty($old_meta)) {
                    $json_str = is_array($old_meta) ? wp_json_encode($old_meta) : $old_meta;
                    $preloaded_b64 = base64_encode($json_str);
                }
            }
        } else {
            $edit_id = 0; 
        }
    }
    ?>

    <script src="https://cdn.tiny.cloud/1/nlr0ea4v5uga6echj2arytqu0442bf8j7lc8srpb4dfia0z6/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/abcjs/6.2.2/abcjs-basic-min.js"></script>
    <script defer src="https://unpkg.com/mathlive"></script>
    
    <script>
    window.MathJax = {
      tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$'], ['\\[', '\\]']] },
      startup: { typeset: false }
    };
    </script>
    <script id="MathJax-script" src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <style>
        .cppm-mock-layout { display: flex; gap: 20px; align-items: flex-start; }
        .cppm-q-sidebar { width: 280px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; flex-shrink: 0; overflow: hidden; }
        .cppm-q-sidebar-header { padding: 15px; background: #e2e8f0; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; }
        .cppm-q-list { max-height: 600px; overflow-y: auto; padding: 10px; }
        .cppm-q-item { padding: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
        .cppm-q-item:hover { border-color: #2874f0; }
        .cppm-q-item.active { background: #e0f2fe; border-color: #38bdf8; font-weight: bold; color: #0369a1; }
        
        .cppm-q-editor { flex: 1; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; }
        .cppm-opt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .cppm-opt-box textarea { width: 100%; height: 80px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; resize: vertical; }
        
        .cppm-preview-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 20px; margin-top: 20px; min-height: 100px; display: none; }
        .music-notation-block { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px; font-family: monospace; color: #92400e; white-space: pre-wrap; margin-bottom: 10px;}
    </style>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <div style="flex:1;">
            <input type="text" id="mock_test_title" class="cppm-form-input" placeholder="Enter Mock Test Title..." style="font-size:20px; font-weight:bold; margin-bottom:0; max-width:400px;" value="<?php echo esc_attr($preloaded_title); ?>">
        </div>
        <div style="display:flex; gap: 10px;">
            <button class="cppm-btn-outline" onclick="openJsonModal()">📥 Bulk Import (JSON)</button>
            <button class="cppm-btn-success" onclick="saveMockTest()">💾 <?php echo $edit_id > 0 ? 'Update' : 'Save'; ?> Test</button>
        </div>
    </div>

    <div class="cppm-mock-layout">
        <div class="cppm-q-sidebar">
            <div class="cppm-q-sidebar-header">
                <strong style="color:#0f172a;">Questions (<span id="q_count">0</span>)</strong>
                <button onclick="addNewQuestion()" style="background:#2874f0; color:#fff; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; font-weight:bold;">+ Add</button>
            </div>
            <div class="cppm-q-list" id="cppm_q_list"></div>
        </div>

        <div class="cppm-q-editor" id="cppm_q_editor_area" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0; color:#0f172a;" id="active_q_label">Question 1</h3>
                <button class="cppm-btn-outline" style="padding: 6px 12px; font-size:12px;" onclick="renderLivePreview()">👁️ Live Preview</button>
            </div>

            <label style="font-weight:bold; color:#475569; display:flex; justify-content:space-between; margin-bottom:8px;">
                Question Text:
                <a href="#" onclick="openMathKeyboard('mce_question', 'Question Text'); return false;" style="color:#2874f0; text-decoration:none; font-weight:normal;">🧮 Add Math</a>
            </label>
            <div style="margin-bottom: 20px; height:200px;"><textarea id="mce_question"></textarea></div>

            <label style="font-weight:bold; color:#475569; display:block; margin-bottom:8px;">Options:</label>
            <div class="cppm-opt-grid">
                <div class="cppm-opt-box"><label style="font-size:12px; font-weight:bold; color:#64748b; display:flex; justify-content:space-between;">Option A <a href="#" onclick="openMathKeyboard('opt_0', 'Option A'); return false;" style="color:#2874f0; text-decoration:none;">🧮</a></label><textarea id="opt_0"></textarea></div>
                <div class="cppm-opt-box"><label style="font-size:12px; font-weight:bold; color:#64748b; display:flex; justify-content:space-between;">Option B <a href="#" onclick="openMathKeyboard('opt_1', 'Option B'); return false;" style="color:#2874f0; text-decoration:none;">🧮</a></label><textarea id="opt_1"></textarea></div>
                <div class="cppm-opt-box"><label style="font-size:12px; font-weight:bold; color:#64748b; display:flex; justify-content:space-between;">Option C <a href="#" onclick="openMathKeyboard('opt_2', 'Option C'); return false;" style="color:#2874f0; text-decoration:none;">🧮</a></label><textarea id="opt_2"></textarea></div>
                <div class="cppm-opt-box"><label style="font-size:12px; font-weight:bold; color:#64748b; display:flex; justify-content:space-between;">Option D <a href="#" onclick="openMathKeyboard('opt_3', 'Option D'); return false;" style="color:#2874f0; text-decoration:none;">🧮</a></label><textarea id="opt_3"></textarea></div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-weight:bold; color:#475569; display:block; margin-bottom:8px;">Correct Answer:</label>
                <select id="correct_ans" class="cppm-form-input" style="max-width:200px;">
                    <option value="0">Option A</option><option value="1">Option B</option>
                    <option value="2">Option C</option><option value="3">Option D</option>
                </select>
            </div>

            <label style="font-weight:bold; color:#475569; display:flex; justify-content:space-between; margin-bottom:8px;">
                Explanation:
                <a href="#" onclick="openMathKeyboard('mce_explanation', 'Explanation'); return false;" style="color:#2874f0; text-decoration:none; font-weight:normal;">🧮 Add Math</a>
            </label>
            <div style="margin-bottom: 20px; height:150px;"><textarea id="mce_explanation"></textarea></div>

            <div id="live_preview_box" class="cppm-preview-box"></div>
        </div>
    </div>

    <div id="json_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:100000; align-items:center; justify-content:center;">
        <div style="background:#fff; width:600px; border-radius:12px; padding:30px; box-shadow:0 20px 40px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0;">Bulk Import</h3>
            <textarea id="json_input" style="width:100%; height:250px; font-family:monospace; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;"></textarea>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button class="cppm-btn-outline" onclick="document.getElementById('json_modal').style.display='none'">Cancel</button>
                <button class="cppm-btn-primary" onclick="processJsonImport()">Import Data</button>
            </div>
        </div>
    </div>

    <div id="math_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:100000; align-items:center; justify-content:center;">
        <div style="background:#fff; width:700px; border-radius:12px; padding:30px; box-shadow:0 20px 40px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;"><h3 style="margin:0;">🧮 Visual Math</h3><button onclick="document.getElementById('math_modal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">✕</button></div>
            <math-field id="visual_math_input" style="width:100%; font-size:24px; padding:15px; border:2px solid #cbd5e1; border-radius:8px; margin-bottom:20px;" virtual-keyboard-mode="manual"></math-field>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:12px; color:#94a3b8;"><strong>Target:</strong> <span id="math_target_label">None</span></div>
                <div style="display:flex; gap:10px;">
                    <button class="cppm-btn-outline" onclick="document.getElementById('visual_math_input').executeCommand('toggleVirtualKeyboard')">⌨️ Keyboard</button>
                    <button class="cppm-btn-primary" onclick="insertVisualMath()">✅ Insert</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // -----------------------------------------------------------------
        // SECURE BASE64 DECODER
        // -----------------------------------------------------------------
        window.edit_id = <?php echo $edit_id; ?>;
        window.preloaded_b64 = "<?php echo esc_js($preloaded_b64); ?>";
        window.activeQIndex = -1;
        
        if (window.preloaded_b64 !== "") {
            try {
                let decodedStr = decodeURIComponent(escape(atob(window.preloaded_b64)));
                window.testData = JSON.parse(decodedStr);
            } catch(e) {
                console.error("Decode failed", e);
                window.testData = { title: "", questions: [] };
            }
        } else {
            window.testData = { title: "", questions: [] };
        }

        tinymce.init({
            selector: '#mce_question, #mce_explanation', menubar: false, plugins: 'code visualblocks image link table charmap',
            toolbar: 'undo redo | bold italic | alignleft aligncenter | bullist numlist | code custom_music_button', height: '100%',
            setup: function(editor) {
                editor.ui.registry.addButton('custom_music_button', { text: '🎵 Insert Music', onAction: function (_) { editor.insertContent('<pre class="music-notation-block">X: 1\nT: Sample\nM: 4/4\nL: 1/4\nK: C\nC D E F | G A B c |]</pre><p><br></p>'); } });
                editor.on('blur change', window.saveActiveQuestionToState);
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            ['opt_0', 'opt_1', 'opt_2', 'opt_3', 'correct_ans'].forEach(id => { let el = document.getElementById(id); if (el) { el.addEventListener('input', window.saveActiveQuestionToState); } });
            
            if (window.testData && window.testData.questions && window.testData.questions.length > 0) {
                window.renderQuestionSidebar();
                window.loadQuestionIntoEditor(0);
            }
        });

        window.addNewQuestion = function() {
            window.testData.questions.push({ q: "<p>New Question</p>", opts: ["Option A", "Option B", "Option C", "Option D"], ans: 0, exp: "<p>Explanation here...</p>" });
            window.renderQuestionSidebar(); window.loadQuestionIntoEditor(window.testData.questions.length - 1);
        };

        window.renderQuestionSidebar = function() {
            let listHTML = ''; document.getElementById('q_count').innerText = window.testData.questions.length;
            if(window.testData.questions.length === 0) { document.getElementById('cppm_q_editor_area').style.display = 'none'; return; }
            window.testData.questions.forEach((q, index) => {
                let isActive = index === window.activeQIndex ? 'active' : '';
                listHTML += `<div class="cppm-q-item ${isActive}" onclick="loadQuestionIntoEditor(${index})"><span>Q ${index + 1}</span><button onclick="deleteQuestion(${index}, event)" style="background:none; border:none; color:#ef4444; cursor:pointer;">🗑️</button></div>`;
            });
            document.getElementById('cppm_q_list').innerHTML = listHTML;
        };

        window.loadQuestionIntoEditor = function(index) {
            if (window.activeQIndex !== -1) window.saveActiveQuestionToState(); 
            window.activeQIndex = index; let qData = window.testData.questions[index];
            document.getElementById('cppm_q_editor_area').style.display = 'block';
            document.getElementById('active_q_label').innerText = `Question ${index + 1}`;
            
            const applyContent = () => {
                if (tinymce.get('mce_question') && tinymce.get('mce_explanation') && tinymce.get('mce_question').initialized) {
                    tinymce.get('mce_question').setContent(qData.q || '');
                    tinymce.get('mce_explanation').setContent(qData.exp || '');
                } else { setTimeout(applyContent, 100); }
            };
            applyContent();
            
            document.getElementById('opt_0').value = qData.opts[0] || ''; document.getElementById('opt_1').value = qData.opts[1] || '';
            document.getElementById('opt_2').value = qData.opts[2] || ''; document.getElementById('opt_3').value = qData.opts[3] || '';
            document.getElementById('correct_ans').value = qData.ans || 0;
            document.getElementById('live_preview_box').style.display = 'none';
            window.renderQuestionSidebar();
        };

        window.saveActiveQuestionToState = function() {
            if (window.activeQIndex === -1 || !window.testData.questions[window.activeQIndex]) return;
            window.testData.questions[window.activeQIndex] = {
                q: tinymce.get('mce_question') ? tinymce.get('mce_question').getContent() : "",
                opts: [ document.getElementById('opt_0').value, document.getElementById('opt_1').value, document.getElementById('opt_2').value, document.getElementById('opt_3').value ],
                ans: parseInt(document.getElementById('correct_ans').value),
                exp: tinymce.get('mce_explanation') ? tinymce.get('mce_explanation').getContent() : ""
            };
        };

        window.deleteQuestion = function(index, e) {
            e.stopPropagation();
            if(confirm("Delete Question " + (index+1) + "?")) { window.testData.questions.splice(index, 1); window.activeQIndex = window.testData.questions.length > 0 ? 0 : -1; window.renderQuestionSidebar(); if(window.activeQIndex !== -1) window.loadQuestionIntoEditor(0); }
        };

        window.renderLivePreview = function() {
            window.saveActiveQuestionToState();
            let box = document.getElementById('live_preview_box'); let qData = window.testData.questions[window.activeQIndex];
            let html = `<div style="margin-bottom:15px;"><strong>Question:</strong><br>${qData.q}</div><div style="margin-bottom:15px;"><strong>Options:</strong><ol type="A" style="margin-top:5px;"><li>${qData.opts[0]}</li><li>${qData.opts[1]}</li><li>${qData.opts[2]}</li><li>${qData.opts[3]}</li></ol></div>`;
            box.innerHTML = html; box.style.display = 'block';
            if (window.MathJax && window.MathJax.typesetPromise) { window.MathJax.typesetPromise([box]).catch(err => console.warn(err.message)); }
            box.querySelectorAll('.music-notation-block').forEach((block, idx) => {
                let renderDiv = document.createElement('div'); renderDiv.id = 'abc-preview-' + idx;
                block.parentNode.replaceChild(renderDiv, block);
                ABCJS.renderAbc(renderDiv.id, (block.innerText || block.textContent).trim(), { responsive: 'resize' });
            });
        };

        window.openJsonModal = function() { document.getElementById('json_modal').style.display = 'flex'; };
        window.processJsonImport = function() {
            try {
                let parsed = JSON.parse(document.getElementById('json_input').value);
                if(Array.isArray(parsed)) {
                    window.testData.questions = window.testData.questions.concat(parsed); document.getElementById('json_modal').style.display = 'none'; document.getElementById('json_input').value = '';
                    window.renderQuestionSidebar(); if(window.activeQIndex === -1 && window.testData.questions.length > 0) window.loadQuestionIntoEditor(0); alert("✅ Imported " + parsed.length + " questions!");
                } else { alert("JSON must be an array of objects: [ {q: ...} ]"); }
            } catch(e) { alert("❌ Invalid JSON format."); }
        };

        // -----------------------------------------------------------------
        // SECURE BASE64 ENCODER
        // -----------------------------------------------------------------
        window.saveMockTest = async function() {
            window.saveActiveQuestionToState();
            window.testData.title = document.getElementById('mock_test_title').value;
            
            if(!window.testData.title) { alert("Please enter a Mock Test Title."); return; }
            if(window.testData.questions.length === 0) { alert("Please add at least one question."); return; }

            const saveBtn = document.querySelector('.cppm-btn-success');
            const originalText = saveBtn.innerText;
            saveBtn.innerText = "💾 Processing...";
            saveBtn.disabled = true;

            try {
                let formData = new URLSearchParams();
                formData.append('action', 'cppm_save_mock_test');
                formData.append('security', window.cppm_mock_nonce);
                formData.append('title', window.testData.title);
                formData.append('edit_id', window.edit_id);
                
                // Encode the JSON string to Base64 to bypass all WordPress Slash filters!
                let jsonStr = JSON.stringify(window.testData);
                let safeBase64 = btoa(unescape(encodeURIComponent(jsonStr))); 
                formData.append('test_data_b64', safeBase64);

                let response = await fetch(window.cppm_ajaxurl, { method: 'POST', body: formData, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
                let result = await response.json();

                if (result.success) {
                    alert("✅ " + result.data.message);
                    window.location.href = '?tab=mock-tests';
                } else { alert("❌ Error: " + result.data); }

            } catch (error) { alert("❌ Network error."); } finally { saveBtn.innerText = originalText; saveBtn.disabled = false; }
        };

        window.currentMathTarget = null;
        window.openMathKeyboard = function(tId, tName) { window.currentMathTarget = tId; document.getElementById('math_target_label').innerText = tName; const mf = document.getElementById('visual_math_input'); mf.setValue(''); document.getElementById('math_modal').style.display = 'flex'; setTimeout(() => { mf.executeCommand('showVirtualKeyboard'); }, 200); };
        window.insertVisualMath = function() {
            if (!window.currentMathTarget) return; const mf = document.getElementById('visual_math_input'); const latex = mf.getValue(); if (!latex) { document.getElementById('math_modal').style.display = 'none'; return; }
            const fMath = `$$${latex}$$`;
            if (window.currentMathTarget === 'mce_question' || window.currentMathTarget === 'mce_explanation') { tinymce.get(window.currentMathTarget).insertContent(fMath + ' '); } else { document.getElementById(window.currentMathTarget).value += fMath; }
            document.getElementById('math_modal').style.display = 'none'; window.saveActiveQuestionToState(); window.renderLivePreview();
        };
    </script>
    <?php
}
