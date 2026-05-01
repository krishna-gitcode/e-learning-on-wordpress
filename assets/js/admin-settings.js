jQuery(document).ready(function($){
    
    // ==========================================
    // 1. VIDEO PLAYLIST REPEATER LOGIC
    // ==========================================
    $('#cppm-add-row').on('click', function(e){
        e.preventDefault();
        $('#cppm-wrapper').append('<div class="cppm-row"><div class="cppm-row-col"><label>Video Title</label><br><input type="text" name="cppm_titles[]" value="" /></div><div class="cppm-row-col"><label>Video URL (MP4)</label><br><input type="url" name="cppm_urls[]" value="" /></div><button class="cppm-btn-remove">X</button></div>');
    });

    $(document).on('click', '.cppm-btn-remove', function(e){
        e.preventDefault();
        $(this).parent('.cppm-row').remove();
    });

    // ==========================================
    // 2. E-BOOK LIBRARY JSON UPLOADER LOGIC
    // ==========================================
    if ( $('#cppm_ebook_docs_json').length ) {
        var docData = $('#cppm_ebook_docs_json').val() ? JSON.parse($('#cppm_ebook_docs_json').val()) : [];
        
        function renderDocs() {
            var html = '';
            docData.forEach(function(doc, index) {
                html += '<div class="cppm-file-row">';
                html += '<input type="text" placeholder="Document Title" class="doc-title" data-index="'+index+'" value="'+(doc.title||'')+'">';
                html += '<input type="text" placeholder="File URL" class="doc-url" data-index="'+index+'" value="'+(doc.url||'')+'" readonly>';
                html += '<button class="cppm-btn-upload" data-index="'+index+'">Choose File</button>';
                html += '<input type="number" placeholder="Price (₹)" class="doc-price" data-index="'+index+'" value="'+(doc.price||0)+'">';
                html += '<button class="cppm-btn-remove-doc" data-index="'+index+'">X</button>';
                html += '</div>';
            });
            $('#cppm-docs-wrapper').html(html);
            $('#cppm_ebook_docs_json').val(JSON.stringify(docData));
        }

        $('#cppm-add-doc').on('click', function(e) {
            e.preventDefault();
            docData.push({title: '', url: '', price: 0});
            renderDocs();
        });

        $(document).on('input', '.doc-title', function() { 
            docData[$(this).data('index')].title = $(this).val(); 
            $('#cppm_ebook_docs_json').val(JSON.stringify(docData)); 
        });
        
        $(document).on('input', '.doc-price', function() { 
            docData[$(this).data('index')].price = $(this).val(); 
            $('#cppm_ebook_docs_json').val(JSON.stringify(docData)); 
        });

        $(document).on('click', '.cppm-btn-remove-doc', function(e) {
            e.preventDefault();
            docData.splice($(this).data('index'), 1);
            renderDocs();
        });

        // Native WordPress Media Library API
        var mediaUploader;
        $(document).on('click', '.cppm-btn-upload', function(e) {
            e.preventDefault();
            var index = $(this).data('index');
            
            if (mediaUploader) { 
                mediaUploader.open(); 
                return; 
            }
            
            mediaUploader = wp.media.frames.file_frame = wp.media({ 
                title: 'Choose E-Book/PDF', 
                button: { text: 'Use this file' }, 
                multiple: false 
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                docData[index].url = attachment.url;
                renderDocs();
            });
            
            mediaUploader.open();
        });

        // Initial Render on page load
        renderDocs();
    }
});