document.addEventListener('DOMContentLoaded', function() {
    
    if (typeof cppmEbookData === 'undefined') return;

    // 1. Initialize Variables from PHP Localization
    var docsData = cppmEbookData.docs;
    var watermarkText = cppmEbookData.watermark;
    var workerSrc = cppmEbookData.workerSrc;
    
    if (!docsData || docsData.length === 0) return;

    pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc;

    var activeDocIndex = 0;
    var activePDF = null;
    var activePageNum = 1;
    var isRendering = false;
    var pagePending = null;
    
    // Zoom & DOM Elements
    var currentScale = 1.0; 
    var baseScale = 1.5; 
    
    var docSelect = document.getElementById('cppm-doc-select');
    var btnPrev = document.getElementById('cppm-prev-page');
    var btnNext = document.getElementById('cppm-next-page');
    var pageInfo = document.getElementById('cppm-page-info');
    var canvasWrap = document.getElementById('cppm-canvas-wrap');
    var pdfCanvas = document.getElementById('cppm-pdf-canvas');
    var wmCanvas = document.getElementById('cppm-watermark-canvas');
    var pdfCtx = pdfCanvas.getContext('2d');
    var wmCtx = wmCanvas.getContext('2d');
    var loader = document.getElementById('cppm-reader-loader');
    var readerArea = document.getElementById('cppm-reader-area');

    // 2. BUFFERING FIX: Progressive Streaming Logic
    function loadPDF(index) {
        if (!docsData[index] || !docsData[index].url) return;
        loader.style.display = 'block';
        
        // OPTIMIZATION: We pass a config object instead of just the URL
        var loadingTask = pdfjsLib.getDocument({
            url: docsData[index].url,
            disableAutoFetch: true,  // Stops PDF.js from downloading the entire book at once
            disableStream: false     // Forces the browser to stream the file in small byte chunks
        });

        loadingTask.promise.then(function(pdfDoc) {
            activePDF = pdfDoc;
            activePageNum = 1;
            renderPage(activePageNum);
        }).catch(function(err) {
            console.error('Error loading PDF:', err);
            loader.innerText = 'Error loading document.';
        });
    }

    // 3. Render Page & Watermark Logic
    function renderPage(num) {
        isRendering = true;
        activePDF.getPage(num).then(function(page) {
            
            // Calculate scale based on container width to make it responsive
            var containerWidth = readerArea.clientWidth - 40; 
            var unscaledViewport = page.getViewport({ scale: 1.0 });
            baseScale = containerWidth / unscaledViewport.width;
            if (baseScale > 2.0) baseScale = 2.0; 

            var viewport = page.getViewport({ scale: baseScale * currentScale });
            
            pdfCanvas.height = viewport.height;
            pdfCanvas.width = viewport.width;
            wmCanvas.height = viewport.height;
            wmCanvas.width = viewport.width;

            canvasWrap.style.width = viewport.width + 'px';
            canvasWrap.style.height = viewport.height + 'px';

            var renderContext = { canvasContext: pdfCtx, viewport: viewport };
            
            var renderTask = page.render(renderContext);
            renderTask.promise.then(function() {
                isRendering = false;
                drawWatermark(viewport.width, viewport.height);
                loader.style.display = 'none';
                
                if (pagePending !== null) {
                    renderPage(pagePending);
                    pagePending = null;
                }
            });

            pageInfo.textContent = num + ' / ' + activePDF.numPages;
            btnPrev.disabled = num <= 1;
            btnNext.disabled = num >= activePDF.numPages;
        });
    }

    function queueRenderPage(num) {
        if (isRendering) { pagePending = num; } 
        else { renderPage(num); }
    }

    function onPrevPage() {
        if (activePageNum <= 1) return;
        activePageNum--;
        queueRenderPage(activePageNum);
    }

    function onNextPage() {
        if (activePageNum >= activePDF.numPages) return;
        activePageNum++;
        queueRenderPage(activePageNum);
    }

    function drawWatermark(w, h) {
        wmCtx.clearRect(0, 0, w, h);
        wmCtx.save();
        wmCtx.translate(w/2, h/2);
        wmCtx.rotate(-Math.PI / 4);
        wmCtx.font = (30 * currentScale) + "px Arial";
        wmCtx.fillStyle = "rgba(128, 128, 128, 0.4)";
        wmCtx.textAlign = "center";
        wmCtx.textBaseline = "middle";
        
        for(let i = -3; i <= 3; i++) {
            for(let j = -3; j <= 3; j++) {
                wmCtx.fillText(watermarkText, i * (300*currentScale), j * (150*currentScale));
            }
        }
        wmCtx.restore();
    }

    // 4. Desktop Controls
    btnPrev.addEventListener('click', onPrevPage);
    btnNext.addEventListener('click', onNextPage);
    
    if (docSelect) {
        docSelect.addEventListener('change', function() {
            activeDocIndex = this.value;
            currentScale = 1.0; 
            canvasWrap.style.transform = 'none';
            loadPDF(activeDocIndex);
        });
    }

    document.getElementById('cppm-zoom-in').addEventListener('click', function() {
        if (currentScale < 3.0) { currentScale += 0.2; queueRenderPage(activePageNum); }
    });
    
    document.getElementById('cppm-zoom-out').addEventListener('click', function() {
        if (currentScale > 0.6) { currentScale -= 0.2; queueRenderPage(activePageNum); }
    });

    // 5. FULLSCREEN LOGIC WITH iOS FALLBACK
    document.getElementById('cppm-fullscreen-btn').addEventListener('click', function() {
        var wrapper = document.querySelector('.cppm-reader-wrapper');

        if (wrapper.requestFullscreen || wrapper.webkitRequestFullscreen || wrapper.msRequestFullscreen) {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                var requestFS = wrapper.requestFullscreen || wrapper.webkitRequestFullscreen || wrapper.msRequestFullscreen;
                requestFS.call(wrapper).catch(err => {
                    console.warn("Native fullscreen blocked, using iOS fallback.", err);
                    toggleIOSFullscreen(wrapper);
                });
            } else {
                var exitFS = document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen;
                if (exitFS) exitFS.call(document);
            }
        } else {
            toggleIOSFullscreen(wrapper);
        }
    });

    function toggleIOSFullscreen(wrapper) {
        wrapper.classList.toggle('cppm-ios-fullscreen');
        if (wrapper.classList.contains('cppm-ios-fullscreen')) {
            document.body.style.overflow = 'hidden'; 
            setTimeout(() => queueRenderPage(activePageNum), 300); 
        } else {
            document.body.style.overflow = '';
            setTimeout(() => queueRenderPage(activePageNum), 300);
        }
    }

    // 6. Mobile Gestures (Swipe & Pinch)
    var touchStartX = 0;
    var touchEndX = 0;
    var initialPinchDist = null;
    var pinchStartScale = 1.0;
    var pinchMultiplier = 1.0;

    readerArea.addEventListener('touchstart', function(e) {
        if (e.touches.length === 2) {
            initialPinchDist = Math.hypot(e.touches[0].pageX - e.touches[1].pageX, e.touches[0].pageY - e.touches[1].pageY);
            pinchStartScale = currentScale;
        } else if (e.touches.length === 1) {
            touchStartX = e.touches[0].screenX;
        }
    }, {passive: false});

    readerArea.addEventListener('touchmove', function(e) {
        if (initialPinchDist && e.touches.length === 2) {
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

    // Boot up the first document
    loadPDF(activeDocIndex);
});