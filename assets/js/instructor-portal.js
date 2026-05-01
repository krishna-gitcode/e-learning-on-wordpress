document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize State securely from PHP Localized Object
    let bookData = cppmPortalData.bookData || [];
    const ajaxurl = cppmPortalData.ajaxUrl;
    const post_id = cppmPortalData.postId;
    
    let activeChapterId = null;
    let activePageId = null;

    const dom = {
        chapterList: document.getElementById('cppm-chapter-list'),
        pageTitle: document.getElementById('cppm-page-title'),
        pageContent: document.getElementById('cppm-page-content'),
        notationPreview: document.getElementById('cppm-abc-preview'),
        saveBtn: document.getElementById('cppm-save-btn'),
        previewBtn: document.getElementById('cppm-preview-btn'),
        addChapterBtn: document.getElementById('cppm-add-chapter'),
        modal: document.getElementById('cppm-preview-modal'),
        modalBody: document.getElementById('cppm-modal-body'),
        modalClose: document.querySelector('.cppm-close')
    };

    if (!dom.chapterList) return; // Exit if not on portal page

    // 2. Render Sidebar
    function renderSidebar() {
        dom.chapterList.innerHTML = '';
        bookData.forEach((chapter, cIndex) => {
            let chDiv = document.createElement('div');
            chDiv.className = 'cppm-chapter-item';
            chDiv.innerHTML = `<strong>${chapter.title || 'Module ' + (cIndex+1)}</strong>`;
            
            let addPageBtn = document.createElement('button');
            addPageBtn.innerText = '+ Add Page';
            addPageBtn.style.cssText = "font-size:11px; margin-left:10px; border:none; background:transparent; color:#2874f0; cursor:pointer;";
            addPageBtn.onclick = () => addPage(chapter.id);
            chDiv.appendChild(addPageBtn);

            (chapter.pages || []).forEach((page, pIndex) => {
                let pgDiv = document.createElement('div');
                pgDiv.className = `cppm-page-item ${activePageId === page.id ? 'active' : ''}`;
                pgDiv.innerText = page.title || `Page ${pIndex+1}`;
                pgDiv.onclick = () => loadPage(chapter.id, page.id);
                chDiv.appendChild(pgDiv);
            });
            dom.chapterList.appendChild(chDiv);
        });
    }

    // 3. Page Logic
    function addPage(chapterId) {
        let chapter = bookData.find(c => c.id === chapterId);
        if(chapter) {
            let newPage = { id: 'page_' + Date.now(), title: 'New Page', content: 'X:1\nT:Scale\nK:C\nCDEFGABc' };
            if(!chapter.pages) chapter.pages = [];
            chapter.pages.push(newPage);
            loadPage(chapterId, newPage.id);
            renderSidebar();
        }
    }

    function loadPage(chapterId, pageId) {
        activeChapterId = chapterId;
        activePageId = pageId;
        let chapter = bookData.find(c => c.id === chapterId);
        let page = chapter.pages.find(p => p.id === pageId);
        
        if(page) {
            dom.pageTitle.value = page.title;
            dom.pageContent.value = page.content;
            renderABCjs();
        }
        renderSidebar();
    }

    // 4. Live ABCjs Rendering
    function renderABCjs() {
        let content = dom.pageContent.value;
        // Check if content looks like ABCjs format (contains X: and K:)
        if(content.includes('X:') && content.includes('K:')) {
            ABCJS.renderAbc("cppm-abc-preview", content, { responsive: "resize" });
            dom.notationPreview.style.display = 'block';
        } else {
            dom.notationPreview.style.display = 'none';
        }
    }

    dom.pageContent.addEventListener('input', function() {
        if(!activePageId) return;
        let chapter = bookData.find(c => c.id === activeChapterId);
        let page = chapter.pages.find(p => p.id === activePageId);
        page.content = this.value;
        renderABCjs();
    });

    dom.pageTitle.addEventListener('input', function() {
        if(!activePageId) return;
        let chapter = bookData.find(c => c.id === activeChapterId);
        let page = chapter.pages.find(p => p.id === activePageId);
        page.title = this.value;
        renderSidebar(); // Update sidebar name instantly
    });

    // 5. AJAX Saving
    dom.saveBtn.addEventListener('click', function() {
        this.innerText = 'Saving...';
        jQuery.post(ajaxurl, {
            action: 'cppm_save_portal_data',
            post_id: post_id,
            book_data: JSON.stringify(bookData)
        }, function(response) {
            dom.saveBtn.innerText = 'Save Course';
            if(response.success) alert('Course Saved Successfully!');
            else alert('Error saving course.');
        });
    });

    // 6. Preview Modal
    dom.previewBtn.addEventListener('click', function() {
        dom.modal.classList.add('open');
        dom.modalBody.innerHTML = `<h1>${dom.pageTitle.value}</h1><div id="cppm-modal-abc"></div>`;
        
        let content = dom.pageContent.value;
        if(content.includes('X:') && content.includes('K:')) {
            ABCJS.renderAbc("cppm-modal-abc", content, { responsive: "resize" });
        } else {
            dom.modalBody.innerHTML += `<p>${content.replace(/\n/g, '<br>')}</p>`;
        }
    });

    dom.modalClose.addEventListener('click', () => dom.modal.classList.remove('open'));

    // Initialize
    if(bookData.length === 0) {
        bookData.push({ id: 'chap_1', title: 'Module 1', pages: [] });
    }
    renderSidebar();
});