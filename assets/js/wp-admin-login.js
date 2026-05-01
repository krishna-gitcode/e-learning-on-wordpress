document.addEventListener('DOMContentLoaded', function() {
    var loginBox = document.getElementById('login');
    
    // Executes the structural DOM hack safely after page load
    if (loginBox) {
        var h1 = loginBox.querySelector('h1');
        var form = document.getElementById('loginform');
        
        if (h1 && form) {
            var wrapper = document.createElement('div');
            wrapper.className = 'cppm-login-wrapper';
            
            loginBox.insertBefore(wrapper, h1);
            wrapper.appendChild(h1);
            wrapper.appendChild(form);
            
            // Replace the generic WordPress logo text with your site name if needed
            var logoLink = h1.querySelector('a');
            if(logoLink && logoLink.innerText.includes('WordPress')) {
                logoLink.innerText = "Sarkari Musician Admin";
            }
        }
    }
});