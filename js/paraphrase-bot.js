jQuery(document).ready(function($) {
    $('#paraphrase-bot-button').on('click', function(e) {
        e.preventDefault();
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
                'text': selectedText
            },
            success: function(response) {
                if (response.success) {
                    tinymce.activeEditor.selection.setContent(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
});
