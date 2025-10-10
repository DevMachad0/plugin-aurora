(function ($) {
    'use strict';

    $(document).on('click', '[data-aurora-copy]', function (event) {
        event.preventDefault();
        var shortcode = $(this).data('aurora-copy');
        if (!shortcode) {
            return;
        }
        const $button = $(event.currentTarget);
        var applyFeedback = function () {
            const successLabel = $button.attr('data-aurora-label-success') || 'Copiado!';
            const defaultLabel = $button.attr('data-aurora-label') || 'Copiar';
            $button.text(successLabel);
            setTimeout(function () {
                $button.text(defaultLabel);
            }, 1600);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode).then(applyFeedback);
        } else {
            var tempInput = document.createElement('input');
            tempInput.type = 'text';
            tempInput.value = shortcode;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            applyFeedback();
        }
    });
})(jQuery);
