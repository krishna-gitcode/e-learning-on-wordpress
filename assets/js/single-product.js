document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Native Share Logic
    const shareBtn = document.getElementById('cppm-share-btn');
    const shareToast = document.getElementById('cppm-share-toast');
    
    if (shareBtn) {
        shareBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = this.getAttribute('data-url');
            const title = this.getAttribute('data-title');
            
            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(console.error);
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    if (shareToast) {
                        shareToast.classList.add('show');
                        setTimeout(() => { shareToast.classList.remove('show'); }, 2000);
                    }
                });
            }
        });
    }

});