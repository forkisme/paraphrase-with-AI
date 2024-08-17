(function($){
    $(document).ready(function() {
        // Create the modal HTML
        var modalHtml = `
            <div id="paraphrase-bot-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 10000;">
                <div style="background: #0073aa; padding: 20px; max-width: 500px; width: 100%; border-radius: 10px; text-align: center; color: white; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                    <h2 style="margin-top: 0; color: white;">Paraphrase Bot Notice</h2>
                    <p><strong>This plugin is not compatible with the Gutenberg editor. Please use the Classic Editor.</strong></p>
                    <button id="close-paraphrase-bot-modal" style="margin-top: 20px; padding: 10px 20px; background: white; color: #0073aa; border: none; border-radius: 5px; cursor: pointer; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);">Dismiss</button>
                </div>
            </div>
        `;

        // Append the modal to the body
        $('body').append(modalHtml);

        // Handle modal dismiss
        $('#close-paraphrase-bot-modal').on('click', function() {
            $('#paraphrase-bot-modal').remove();
        });
    });
})(jQuery);
