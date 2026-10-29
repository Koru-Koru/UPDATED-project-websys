$(function () {
    'use strict';

    $('.recovery-request-form').each(function () {
        const $form = $(this);
        const $verified = $form.find('.recovery-verified');
        const sync = () => $form.find('.recovery-generate, .recovery-save').prop('disabled', !$verified.is(':checked'));
        $verified.on('change', sync);
        sync();
        $form.on('submit', function (event) {
            const action = event.originalEvent.submitter?.value || 'send';
            if (action === 'delete' && !window.confirm('Delete this recovery request and invalidate its reset link? The customer account will be kept.')) {
                event.preventDefault();
                return;
            }
            if (action === 'send') $form.find('.recovery-generate').prop('disabled', true).text('Sending…');
        });
    });

});
