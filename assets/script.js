jQuery(document).ready(function($) {
    $('#start-update').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        $('#progress-container').show();
        $('#results-container').hide();
        $('#progress-fill').css('width', '0%');
        $('#progress-text').text('Processing...');
        
        $.ajax({
            url: altTextUpdater.ajax_url,
            type: 'POST',
            data: {
                action: 'update_alt_texts',
                nonce: altTextUpdater.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#progress-fill').css('width', '100%');
                    $('#progress-text').text('Complete!');
                    
                    var resultsHtml = '<div class="notice notice-success"><p><strong>Update Complete!</strong></p></div>';
                    resultsHtml += '<ul>';
                    resultsHtml += '<li>Total images scanned: ' + data.total + '</li>';
                    resultsHtml += '<li>Images updated: ' + data.updated + '</li>';
                    resultsHtml += '<li>Images skipped: ' + data.skipped + '</li>';
                    resultsHtml += '</ul>';
                    
                    $('#results-content').html(resultsHtml);
                    $('#results-container').show();
                } else {
                    alert('Error: ' + response.data);
                }
                button.prop('disabled', false);
            },
            error: function() {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false);
                $('#progress-container').hide();
            }
        });
    });
});
