document.addEventListener('DOMContentLoaded', function() {
    // 1. Mobile Routing Logic
    if (window.innerWidth <= 768) {
        var path = window.location.pathname.replace(/\/$/, "");
        if (path.endsWith('my-account')) { document.body.classList.add('cppm-is-mobile-root'); } 
        else { 
            document.body.classList.add('cppm-is-mobile-subpage');
            var contentArea = document.querySelector('.woocommerce-MyAccount-content');
            if(contentArea) { contentArea.insertAdjacentHTML('afterbegin', '<a href="' + cppmAccountData.homeUrl + '/my-account/" class="cppm-mobile-back-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Back to Menu</a>'); }
        }
    }

    // 2. SVG Icon Injection
    const icons = {
        'orders': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline></svg>',
        'account_settings_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'instructor_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
        'admin_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
        'legal_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        'customer-logout': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>'
    };

    for (const [endpoint, svg] of Object.entries(icons)) {
        const linkItem = document.querySelector(`.woocommerce-MyAccount-navigation-link--${endpoint} a`);
        if (linkItem) { linkItem.innerHTML = svg + '<span>' + linkItem.innerHTML + '</span>'; }
    }
    
    // 3. Password Modal Logic
    const pwdLink = document.querySelector('.woocommerce-MyAccount-navigation-link--change-password a');
    if(pwdLink) pwdLink.setAttribute('id', 'cppm-trigger-pwd');

    const pwdTrigger = document.getElementById('cppm-trigger-pwd');
    const pwdModal = document.getElementById('cppm-pwd-modal');
    
    if(pwdTrigger && pwdModal) {
        pwdTrigger.addEventListener('click', (e) => { e.preventDefault(); pwdModal.classList.add('open'); });
        document.getElementById('cppm-close-pwd').addEventListener('click', () => { pwdModal.classList.remove('open'); });
        document.getElementById('cppm_submit_pwd').addEventListener('click', () => {
            const oldPwd = document.getElementById('cppm_old_pwd').value;
            const newPwd = document.getElementById('cppm_new_pwd').value;
            const notice = document.querySelector('.cppm-pwd-notice');
            if(!oldPwd || !newPwd) { notice.style.display='block'; notice.style.background='#fee2e2'; notice.style.color='#991b1b'; notice.innerText='Please fill both fields.'; return; }
            document.getElementById('cppm_submit_pwd').innerText = 'Updating...';
            
            fetch(cppmAccountData.ajaxUrl, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: 'action=cppm_change_password_ajax&old_pwd=' + encodeURIComponent(oldPwd) + '&new_pwd=' + encodeURIComponent(newPwd)
            }).then(r => r.json()).then(res => {
                notice.style.display='block';
                if(res.success) { 
                    notice.style.background='#dcfce7'; notice.style.color='#166534'; notice.innerText=res.data.message; 
                    setTimeout(() => window.location.href = cppmAccountData.logoutUrl, 2000);
                } else { 
                    notice.style.background='#fee2e2'; notice.style.color='#991b1b'; notice.innerText=res.data.message; 
                    document.getElementById('cppm_submit_pwd').innerText = 'Update Password'; 
                }
            });
        });
    }
});