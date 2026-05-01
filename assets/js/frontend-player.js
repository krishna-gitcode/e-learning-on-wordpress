document.addEventListener("DOMContentLoaded", function() {
    
    if (typeof cppmPlayerData === 'undefined') return;
    
    var ajaxUrl = cppmPlayerData.ajaxUrl;
    var ajaxNonce = cppmPlayerData.ajaxNonce;

    var isDark = localStorage.getItem('cppm_theme') === 'dark';
    var containers = document.querySelectorAll('.cppm-container');
    
    containers.forEach(function(wrap) {
        var uid = wrap.getAttribute('data-uid');
        renderBookmarks(uid);
        if (isDark) {
            wrap.classList.add('cppm-dark-mode');
            var btn = wrap.querySelector('.cppm-ux-darkmode');
            if(btn) btn.innerText = '☀️';
        }
    });

    function filterComments(uid, target) {
        var wrap = document.getElementById('playlist-' + uid);
        if(!wrap) return;
        wrap.querySelectorAll('.cppm-custom-comment').forEach(function(c) {
            var cIdx = c.getAttribute('data-video-index');
            c.style.display = (cIdx == target || cIdx === 'all') ? 'flex' : 'none'; 
        });
    }

    function renderBookmarks(uid) {
        var notesArea = document.getElementById('cppm-notes-input-' + uid);
        var bookmarksContainer = document.getElementById('cppm-bookmarks-' + uid);
        if(!notesArea || !bookmarksContainer) return;
        
        var text = notesArea.value;
        var regex = /\[(\d{1,2}):(\d{2})\]/g;
        var match;
        var stamps = [];
        while ((match = regex.exec(text)) !== null) { 
            if (!stamps.includes(match[0])) stamps.push(match[0]); 
        }
        var html = '';
        stamps.forEach(function(stamp) {
            var timeStr = stamp.replace('[', '').replace(']', '');
            var parts = timeStr.split(':');
            var totalSecs = parseInt(parts[0]) * 60 + parseInt(parts[1]);
            html += '<button type="button" class="cppm-timestamp-link" data-seek="'+totalSecs+'" style="background:var(--bg-hover); color:var(--brand); border:1px solid var(--border); padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer; margin-right:10px; margin-bottom:10px;">⏱ '+timeStr+'</button>';
        });
        if (html !== '') {
            bookmarksContainer.innerHTML = '<div style="margin-top:20px; padding-top:20px; border-top:1px solid var(--border);"><strong style="font-size:0.85rem; color:var(--text-sec); display:block; margin-bottom:12px;">Quick Jump Bookmarks:</strong>' + html + '</div>';
        } else {
            bookmarksContainer.innerHTML = '';
        }
    }

    document.body.addEventListener('input', function(e) {
        if (e.target.matches('.cppm-course-search')) {
            var term = e.target.value.toLowerCase();
            var wrap = e.target.closest('.cppm-container');
            if(!wrap) return;
            wrap.querySelectorAll('.cppm-item').forEach(function(item) {
                var titleText = item.innerText.toLowerCase();
                item.style.display = titleText.includes(term) ? 'flex' : 'none';
            });
        }
        
        if (e.target.matches('.cppm-yt-textarea')) {
            e.target.style.height = 'auto';
            e.target.style.height = (e.target.scrollHeight) + 'px';
            
            var form = e.target.closest('form');
            var submitBtn = form.querySelector('.cppm-yt-submit');
            if(submitBtn) {
                if(e.target.value.trim().length > 0) {
                    submitBtn.style.background = 'var(--brand)';
                    submitBtn.style.color = '#fff';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.disabled = false;
                    
                    var actions = form.querySelector('.cppm-yt-form-actions');
                    if(actions) actions.classList.add('active');
                } else {
                    submitBtn.style.background = 'var(--bg-hover)';
                    submitBtn.style.color = 'var(--text-sec)';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.disabled = true;
                }
            }
        }
    });

    document.body.addEventListener('keydown', function(e) {
        if (e.target.matches('.cppm-yt-textarea')) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); 
                var form = e.target.closest('form');
                var submitBtn = form.querySelector('.cppm-yt-submit');
                
                if (submitBtn && !submitBtn.disabled) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }
        }
    });

    document.body.addEventListener('click', function(e) {
        
        var deleteBtn = e.target.closest('.cppm-yt-delete');
        if (deleteBtn) {
            e.preventDefault();
            if (!confirm("Are you sure you want to delete this comment?")) return;
            
            var cid = deleteBtn.getAttribute('data-cid');
            var commentBox = deleteBtn.closest('.cppm-yt-comment');
            var wrap = deleteBtn.closest('.cppm-container');
            var uid = wrap.getAttribute('data-uid');

            var fdDel = new FormData();
            fdDel.append('action', 'cppm_delete_comment');
            fdDel.append('comment_id', cid);
            fdDel.append('security', ajaxNonce);

            deleteBtn.innerText = "Deleting...";
            deleteBtn.style.pointerEvents = "none";

            fetch(ajaxUrl, { method: "POST", body: fdDel })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    commentBox.style.opacity = "0";
                    setTimeout(() => { 
                        commentBox.remove(); 
                        var countEl = document.getElementById('cppm-comment-count-' + uid);
                        if(countEl) {
                            var currentCount = parseInt(countEl.innerText);
                            if (currentCount > 0) countEl.innerText = currentCount - 1;
                        }
                    }, 300);
                } else {
                    alert(data.data);
                    deleteBtn.innerText = "Delete";
                    deleteBtn.style.pointerEvents = "auto";
                }
            })
            .catch(err => {
                alert("Connection error.");
                deleteBtn.innerText = "Delete";
                deleteBtn.style.pointerEvents = "auto";
            });
        }

        var darkBtn = e.target.closest('.cppm-ux-darkmode');
        if (darkBtn) {
            e.preventDefault();
            var wrap = darkBtn.closest('.cppm-container');
            if (wrap) {
                var isNowDark = wrap.classList.toggle('cppm-dark-mode');
                darkBtn.innerText = isNowDark ? '☀️' : '🌙';
                localStorage.setItem('cppm_theme', isNowDark ? 'dark' : 'light');
            }
        }

        var switchBtn = e.target.closest('.cppm-switch-trigger');
        if (switchBtn) {
            e.preventDefault();
            var wrap = switchBtn.closest('.cppm-container');
            if(!wrap) return;
            var uid = wrap.getAttribute('data-uid');
            var pid = wrap.getAttribute('data-pid');
            var target = switchBtn.getAttribute('data-target');
            var title = switchBtn.getAttribute('data-title');

            wrap.querySelectorAll('.cppm-switch-trigger').forEach(function(b){ b.classList.remove('active'); });
            switchBtn.classList.add('active');

            var doneBtn = document.getElementById('done-btn-' + uid);
            var isDone = switchBtn.getAttribute('data-completed') === 'true';
            if(doneBtn) {
                doneBtn.style.display = isDone ? 'none' : 'block';
                doneBtn.setAttribute('data-idx', target);
            }

            var titleEl = document.getElementById('cppm-active-title-' + uid);
            var labelEl = document.getElementById('cppm-active-label-' + uid);
            if(titleEl) titleEl.innerText = title;
            if(labelEl) labelEl.innerText = 'Lesson ' + (parseInt(target) + 1);

            var idxField = document.getElementById('cppm-active-index-' + uid);
            if(idxField) idxField.value = target;
            filterComments(uid, target);

            var frame = document.getElementById('cppm-video-frame-' + uid);
            if(frame) {
                var slides = frame.querySelectorAll('.cppm-player-slide');
                slides.forEach(function(slide) { slide.style.display = 'none'; });
                
                var targetSlide = document.getElementById('player-slide-' + uid + '-' + target);
                if(targetSlide) targetSlide.style.display = 'block';
            }

            var fdSave = new FormData();
            fdSave.append("action", "cppm_save_last_video");
            fdSave.append("playlist_id", pid);
            fdSave.append("video_index", target);
            fetch(ajaxUrl, { method: "POST", body: fdSave });
        }

        var theaterBtn = e.target.closest('.cppm-ux-theater');
        if (theaterBtn) {
            e.preventDefault();
            var wrap = theaterBtn.closest('.cppm-container');
            if(wrap) wrap.classList.toggle('cppm-theater-mode');
        }

        var doneBtn = e.target.closest('.cppm-ux-done');
        if (doneBtn) {
            e.preventDefault();
            var wrap = doneBtn.closest('.cppm-container');
            var uid = wrap.getAttribute('data-uid');
            var pid = doneBtn.getAttribute('data-pid');
            var idx = doneBtn.getAttribute('data-idx');
            
            doneBtn.style.display = 'none';
            
            var navBtn = document.getElementById("nav-" + uid + "-" + idx);
            if(navBtn) navBtn.setAttribute('data-completed', 'true');

            var dot = document.getElementById("dot-" + uid + "-" + idx);
            if(dot) { dot.style.borderColor = '#10b981'; dot.style.background = '#10b981'; }
            
            var cLabel = document.getElementById("count-" + uid);
            if(cLabel && wrap) {
                var cur = parseInt(cLabel.innerText) + 1;
                cLabel.innerText = cur;
                var total = parseInt(wrap.getAttribute('data-total'));
                var fill = document.getElementById("fill-" + uid);
                if(fill) fill.style.width = ((cur / total) * 100) + "%";

                if (cur === total) {
                    var certBtn = document.getElementById("cppm-cert-btn-" + uid);
                    if (certBtn) certBtn.style.display = 'block';

                    var overlay = document.createElement('div');
                    overlay.innerHTML = '<div style="position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:99999; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.85); backdrop-filter:blur(5px); animation: cppmFadeInOut 4s forwards;"><div style="text-align:center; transform:scale(0.8); animation: cppmPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;"><h1 style="font-size:5rem; margin:0;">🎉</h1><h2 style="color:#10b981; font-size:2.5rem; margin:10px 0; font-weight:800;">Course Completed!</h2><p style="color:#4b5563; font-size:1.2rem; font-weight:600;">Amazing work. You reached 100%.</p></div></div><style>@keyframes cppmFadeInOut { 0%{opacity:0;} 10%{opacity:1;} 80%{opacity:1;} 100%{opacity:0;} } @keyframes cppmPop { to {transform:scale(1);} }</style>';
                    document.body.appendChild(overlay);
                    setTimeout(function(){ overlay.remove(); }, 4000);
                }
            }
            
            var fd = new FormData();
            fd.append("action", "cppm_mark_completed");
            fd.append("playlist_id", pid);
            fd.append("video_index", idx);
            fetch(ajaxUrl, { method: "POST", body: fd });
        }

        var tsLink = e.target.closest('.cppm-timestamp-link');
        if (tsLink) {
            e.preventDefault();
            var wrap = tsLink.closest('.cppm-container');
            var targetSeconds = parseInt(tsLink.getAttribute('data-seek'));
            var frame = wrap.querySelector('.cppm-video-frame');
            if (frame) {
                var player = frame.querySelector('presto-player');
                if (player && player.player && typeof player.player.play === 'function') {
                    player.player.currentTime = targetSeconds;
                    player.player.play();
                    frame.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }

        var insertBtn = e.target.closest('.cppm-ux-timestamp');
        if (insertBtn) {
            e.preventDefault();
            var wrap = insertBtn.closest('.cppm-container');
            var uid = wrap.getAttribute('data-uid');
            var notesArea = document.getElementById('cppm-notes-input-' + uid);
            
            var frame = wrap.querySelector('.cppm-video-frame');
            if (frame && notesArea) {
                var player = frame.querySelector('presto-player');
                if (player && player.player) {
                    var time = Math.floor(player.player.currentTime || 0);
                    var min = Math.floor(time / 60);
                    var sec = time % 60;
                    var ts = '[' + min + ':' + (sec < 10 ? '0' : '') + sec + '] ';
                    
                    var cursorPos = notesArea.selectionStart || notesArea.value.length;
                    var textBefore = notesArea.value.substring(0, cursorPos);
                    var textAfter = notesArea.value.substring(cursorPos);
                    notesArea.value = textBefore + ts + textAfter;
                    notesArea.focus();
                } else {
                    alert("Start the video first to grab a timestamp.");
                }
            }
        }

        var saveNotesBtn = e.target.closest('.cppm-ux-save-notes');
        if (saveNotesBtn) {
            e.preventDefault();
            var wrap = saveNotesBtn.closest('.cppm-container');
            var uid = wrap.getAttribute('data-uid');
            var pid = wrap.getAttribute('data-pid');
            var notesArea = document.getElementById('cppm-notes-input-' + uid);
            
            var origText = saveNotesBtn.innerText;
            saveNotesBtn.innerText = "Saving...";
            saveNotesBtn.disabled = true;

            var fd = new FormData();
            fd.append('action', 'cppm_save_notes');
            fd.append('playlist_id', pid);
            fd.append('notes', notesArea.value);

            fetch(ajaxUrl, { method: "POST", body: fd })
            .then(function(res){ return res.json(); })
            .then(function(data){
                saveNotesBtn.innerText = origText;
                saveNotesBtn.disabled = false;
                renderBookmarks(uid);
                
                var status = document.getElementById('cppm-notes-status-' + uid);
                if (status) {
                    status.style.opacity = '1';
                    setTimeout(function(){ status.style.opacity = '0'; }, 3000);
                }
            });
        }
        
        if (e.target.matches('.cppm-yt-cancel')) {
            e.preventDefault();
            var form = e.target.closest('form');
            var txt = form.querySelector('.cppm-yt-textarea');
            var btn = form.querySelector('.cppm-yt-submit');
            if(txt) { txt.value = ''; txt.style.height = 'auto'; }
            if(btn) { btn.style.background = 'var(--bg-hover)'; btn.style.color = 'var(--text-sec)'; btn.style.cursor = 'not-allowed'; btn.disabled = true; }
            var actions = form.querySelector('.cppm-yt-form-actions');
            if(actions) actions.classList.remove('active');
        }
    });

    // AJAX Comments Handler
    document.body.addEventListener('submit', function(e) {
        var form = e.target;
        
        if (form.matches('.cppm-ajax-comment-form')) {
            e.preventDefault();
            var wrap = form.closest('.cppm-container');
            var uid = wrap.getAttribute('data-uid');
            
            var activeIdx = document.getElementById('cppm-active-index-' + uid).value;
            var activeBtn = document.getElementById('nav-' + uid + '-' + activeIdx);
            var activeTitle = activeBtn ? activeBtn.getAttribute('data-title') : '';

            var submitBtn = form.querySelector('button[type="submit"]');
            var origText = "Comment";
            if(submitBtn) {
                origText = submitBtn.innerText;
                submitBtn.innerText = "Posting...";
                submitBtn.disabled = true;
            }

            var fd = new FormData(form);
            fd.append('action', 'cppm_submit_comment');
            fd.set('cppm_vid_index', activeIdx);
            fd.set('cppm_vid_title', activeTitle);
            
            fetch(ajaxUrl, { method: "POST", body: fd })
            .then(function(res){ return res.json(); })
            .then(function(data){
                if(submitBtn) {
                    submitBtn.innerText = origText;
                    submitBtn.style.background = 'var(--bg-hover)';
                    submitBtn.style.color = 'var(--text-sec)';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.disabled = true;
                }
                if(data.success) {
                    var wrapList = document.getElementById('cppm-comments-wrapper-' + uid);
                    var noMsg = document.getElementById('cppm-no-comments-msg-' + uid);
                    if(noMsg) noMsg.remove();
                    if(wrapList) wrapList.insertAdjacentHTML('afterbegin', data.data);
                    
                    var txt = form.querySelector('.cppm-yt-textarea');
                    if(txt) { txt.value = ''; txt.style.height = 'auto'; }
                    var actions = form.querySelector('.cppm-yt-form-actions');
                    if(actions) actions.classList.remove('active');

                    var countEl = document.getElementById('cppm-comment-count-' + uid);
                    if(countEl) countEl.innerText = parseInt(countEl.innerText) + 1;
                } else {
                    alert("Notice: " + data.data);
                }
            }).catch(function(err){
                if(submitBtn) {
                    submitBtn.innerText = origText;
                    submitBtn.disabled = false;
                }
            });
        }
    });

    // Smart Auto-Advance
    document.body.addEventListener('ended', function(e) {
        var wrap = e.target.closest('.cppm-container');
        if(!wrap) return;
        
        var frame = wrap.querySelector('.cppm-video-frame');
        if (!frame || !frame.contains(e.target)) return; 

        var uid = wrap.getAttribute('data-uid');
        var totalVideos = parseInt(wrap.getAttribute('data-total'));
        var currentIndex = parseInt(document.getElementById('cppm-active-index-' + uid).value);

        var doneBtn = document.getElementById("done-btn-" + uid);
        if(doneBtn && doneBtn.style.display !== 'none') {
            doneBtn.click(); 
        }

        var nextIndex = currentIndex + 1;
        if (nextIndex < totalVideos) {
            var toast = document.getElementById("cppm-toast-" + uid);
            if (toast) toast.classList.add('show');
            setTimeout(function() {
                if (toast) toast.classList.remove('show');
                var nextBtn = document.getElementById("nav-" + uid + "-" + nextIndex);
                if(nextBtn) nextBtn.click(); 
            }, 3000);
        }
    }, true); 

});