document.addEventListener("DOMContentLoaded", function() {
    var customerLogin = document.getElementById('customer_login');
    var wooWrapper = document.querySelector('.woocommerce');
    if(!wooWrapper) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'cppm-login-split-wrapper';
    
    var banner = document.createElement('div');
    banner.className = 'cppm-login-banner';
    banner.innerHTML = '<div class="cppm-banner-content"><h2>Master Your Music Journey</h2><p>Log in or create an account to access premium courses, track your orders, and learn from the best.</p><ul class="cppm-feature-list"><li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> High-Quality Video Modules</li><li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Interactive Sheet Music</li><li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Mock Tests & Analytics</li></ul></div>';

    var formPanel = document.createElement('div');
    formPanel.className = 'cppm-login-form-panel';
    
    var mobileTopBar = document.createElement('div');
    mobileTopBar.className = 'cppm-mobile-topbar';
    mobileTopBar.innerHTML = '<a href="' + cppmAuthData.homeUrl + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Back</a>';
    formPanel.appendChild(mobileTopBar);

    var logoDiv = document.createElement('div');
    logoDiv.className = 'cppm-login-logo';
    logoDiv.style.backgroundImage = "url('" + cppmAuthData.logoUrl + "')";
    wooWrapper.insertBefore(logoDiv, wooWrapper.firstChild); 
    
    var mobileWelcome = document.createElement('h2');
    mobileWelcome.className = 'cppm-mobile-welcome';
    mobileWelcome.innerText = 'Welcome Back';
    wooWrapper.insertBefore(mobileWelcome, logoDiv.nextSibling);

    if(customerLogin) {
        var col1 = customerLogin.querySelector('.u-column1');
        var col2 = customerLogin.querySelector('.u-column2');
        if (col1 && col2) {
            var tabsHTML = '<div class="cppm-auth-tabs"><button type="button" class="cppm-tab active" data-target="login">Log In</button><button type="button" class="cppm-tab" data-target="register">Sign Up</button></div>';
            customerLogin.insertAdjacentHTML('afterbegin', tabsHTML);
            var tabs = document.querySelectorAll('.cppm-tab');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    if (this.dataset.target === 'login') {
                        col1.style.display = 'block';
                        col2.style.display = 'none';
                        mobileWelcome.innerText = 'Welcome Back';
                    } else {
                        col1.style.display = 'none';
                        col2.style.display = 'block';
                        mobileWelcome.innerText = 'Create Account';
                    }
                });
            });
        }
    }

    var userField = document.getElementById('username');
    var passField = document.getElementById('password');
    if(userField) userField.placeholder = "Email Address or Username";
    if(passField) passField.placeholder = "Password";

    var regEmail = document.getElementById('reg_email');
    var regUser = document.getElementById('reg_username');
    var regPass = document.getElementById('reg_password');
    if(regEmail) regEmail.placeholder = "Email Address";
    if(regUser) regUser.placeholder = "Choose a Username";
    if(regPass) regPass.placeholder = "Create a Password";

    var lostPwdLink = document.querySelector('.woocommerce-LostPassword a');
    var lostPwdContainer = document.querySelector('.woocommerce-LostPassword');
    if(lostPwdLink) { lostPwdLink.innerText = 'Forgot password?'; }
    if(lostPwdContainer && !document.querySelector('.cppm-return-site-bottom')) {
        var returnLink = document.createElement('a');
        returnLink.href = cppmAuthData.homeUrl;
        returnLink.className = 'cppm-return-site-bottom';
        returnLink.innerHTML = '&larr; Back to website';
        lostPwdContainer.appendChild(returnLink);
    }

    wooWrapper.parentNode.insertBefore(wrapper, wooWrapper);
    formPanel.appendChild(wooWrapper);
    wrapper.appendChild(banner);
    wrapper.appendChild(formPanel);
});