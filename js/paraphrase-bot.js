jQuery(document).ready(function($) {
    $('#paraphrase-bot-button').on('click', function(e) {
        e.preventDefault();
        
        // Get the selected text from TinyMCE
        var selectedText = tinymce.activeEditor.selection.getContent({format: 'text'});

        if (selectedText.length === 0) {
            alert('Please select some text to paraphrase.');
            return;
        }

        $.ajax({
            url: paraphraseBot.ajax_url,
            type: 'POST',
            data: {
                'action': 'paraphrase_text',
                'text': selectedText,
                'security': paraphraseBot.nonce // Include nonce for security
            },
            success: function(response) {
                if (response.success) {
                    // Replace the selected text with the paraphrased version
                    tinymce.activeEditor.selection.setContent(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX errors
                console.error('AJAX Error: ' + status + ' - ' + error);
            }
        });
    });
});
