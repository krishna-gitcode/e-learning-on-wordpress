document.addEventListener('DOMContentLoaded', function() {
    if (typeof cppmExamData === 'undefined') return;

    let questions = cppmExamData.questions;
    let durationSecs = cppmExamData.duration * 60;
    let ajaxUrl = cppmExamData.ajaxUrl;
    let testId = cppmExamData.testId;
    let nonce = cppmExamData.nonce;

    let currentIndex = 0;
    let answers = {};  // Stores selected option index
    let statuses = {}; // 'answered', 'not-answered', 'review'
    let timerInterval;

    // DOM Elements
    const elQText = document.getElementById('cppm-q-text');
    const elAbcContainer = document.getElementById('cppm-q-abc');
    const elOptions = document.getElementById('cppm-q-options');
    const elPalette = document.getElementById('cppm-palette-grid');
    const elTimer = document.getElementById('cppm-timer-display');
    const elQNum = document.getElementById('cppm-q-num');
    
    // Init
    if (questions.length === 0) return;
    initPalette();
    renderQuestion(currentIndex);
    startTimer();

    // 1. Render Question
    function renderQuestion(index) {
        let q = questions[index];
        elQNum.innerText = `Question ${index + 1} of ${questions.length}`;
        elQText.innerHTML = q.text;

        // Render Music Notation if present
        if (q.abc && q.abc.trim() !== '') {
            elAbcContainer.style.display = 'block';
            ABCJS.renderAbc('cppm-q-abc', q.abc, { responsive: "resize" });
        } else {
            elAbcContainer.style.display = 'none';
        }

        // Render Options
        let html = '';
        q.options.forEach((opt, optIndex) => {
            let isChecked = (answers[q.id] == optIndex) ? 'checked' : '';
            let isSelectedClass = (answers[q.id] == optIndex) ? 'selected' : '';
            html += `
                <label class="cppm-option-label ${isSelectedClass}">
                    <input type="radio" name="cppm_q_opt" value="${optIndex}" ${isChecked} onchange="cppmSelectOption(this)">
                    ${opt}
                </label>
            `;
        });
        elOptions.innerHTML = html;

        // Update Palette Active State
        document.querySelectorAll('.cppm-palette-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(`pal-${index}`).classList.add('active');

        // Mark as viewed (not-answered) if not touched yet
        if (!statuses[q.id]) {
            statuses[q.id] = 'not-answered';
            updatePaletteBtn(index);
        }
    }

    // Handle Option Click UI
    window.cppmSelectOption = function(radio) {
        document.querySelectorAll('.cppm-option-label').forEach(lbl => lbl.classList.remove('selected'));
        radio.closest('.cppm-option-label').classList.add('selected');
    };

    // 2. Button Actions
    document.getElementById('btn-save-next').addEventListener('click', () => {
        saveCurrentState('answered');
        if (currentIndex < questions.length - 1) { currentIndex++; renderQuestion(currentIndex); }
    });

    document.getElementById('btn-review-next').addEventListener('click', () => {
        saveCurrentState('review');
        if (currentIndex < questions.length - 1) { currentIndex++; renderQuestion(currentIndex); }
    });

    document.getElementById('btn-clear').addEventListener('click', () => {
        let q = questions[currentIndex];
        delete answers[q.id];
        statuses[q.id] = 'not-answered';
        renderQuestion(currentIndex);
        updatePaletteBtn(currentIndex);
    });

    function saveCurrentState(forceStatus) {
        let q = questions[currentIndex];
        let checked = document.querySelector('input[name="cppm_q_opt"]:checked');
        
        if (checked) {
            answers[q.id] = checked.value;
            statuses[q.id] = forceStatus || 'answered';
        } else {
            statuses[q.id] = forceStatus === 'review' ? 'review' : 'not-answered';
        }
        updatePaletteBtn(currentIndex);
    }

    // 3. Palette Logic
    function initPalette() {
        let html = '';
        questions.forEach((q, i) => {
            html += `<button type="button" id="pal-${i}" class="cppm-palette-btn" onclick="cppmJumpTo(${i})">${i + 1}</button>`;
        });
        elPalette.innerHTML = html;
    }

    window.cppmJumpTo = function(i) {
        saveCurrentState(statuses[questions[currentIndex].id]); // save current before jumping
        currentIndex = i;
        renderQuestion(currentIndex);
    };

    function updatePaletteBtn(index) {
        let q = questions[index];
        let btn = document.getElementById(`pal-${index}`);
        btn.className = 'cppm-palette-btn active'; // reset
        if (statuses[q.id]) btn.classList.add(statuses[q.id]);
    }

    // 4. Timer Logic
    function startTimer() {
        timerInterval = setInterval(() => {
            durationSecs--;
            if (durationSecs <= 0) {
                clearInterval(timerInterval);
                submitExam(true); // Auto submit
            } else {
                let m = Math.floor(durationSecs / 60);
                let s = durationSecs % 60;
                elTimer.innerText = `${m}:${s < 10 ? '0' : ''}${s}`;
            }
        }, 1000);
    }

    // 5. Submit Exam
    document.getElementById('btn-submit').addEventListener('click', () => {
        if (confirm("Are you sure you want to submit the exam? You cannot change answers after submission.")) {
            saveCurrentState();
            submitExam(false);
        }
    });

    function submitExam(isAuto) {
        clearInterval(timerInterval);
        
        let wrapper = document.getElementById('cppm-exam-workspace');
        wrapper.innerHTML = `<div style="padding:100px; text-align:center;">
            <h2>${isAuto ? "Time's Up!" : "Submitting Exam..."}</h2>
            <p>Please wait while we calculate your score and rank...</p>
        </div>`;

        let fd = new FormData();
        fd.append('action', 'cppm_submit_mock_test');
        fd.append('test_id', testId);
        fd.append('answers', JSON.stringify(answers));
        fd.append('time_taken', (cppmExamData.duration * 60) - durationSecs);
        fd.append('security', nonce);

        fetch(ajaxUrl, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                wrapper.innerHTML = data.data; // Render Scorecard
                // We must re-init ABCjs for the explanations since new DOM was injected!
                if(typeof ABCJS !== 'undefined') {
                    let abcDivs = wrapper.querySelectorAll('.cppm-exp-abc');
                    abcDivs.forEach(div => {
                        let abcText = div.getAttribute('data-abc');
                        if(abcText) ABCJS.renderAbc(div.id, abcText, { responsive: "resize" });
                    });
                }
            } else {
                wrapper.innerHTML = `<h3 style="color:red; text-align:center;">Error: ${data.data}</h3>`;
            }
        }).catch(err => {
            wrapper.innerHTML = `<h3 style="color:red; text-align:center;">Connection Error. Please contact support.</h3>`;
        });
    }
});