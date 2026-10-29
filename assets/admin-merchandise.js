$(function () {
    'use strict';

    let pending = null;
    $('.collection-confirm-form').on('submit', function (event) {
        event.preventDefault();
        pending = this;
        $('#collection-confirm-number').text('#' + $(this).attr('data-order'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('collection-confirm-modal')).show();
    });
    $('#collection-confirm-submit').on('click', function () {
        if (!pending) {
            return;
        }
        $(this).prop('disabled', true);
        $('<input>').attr({type: 'hidden', name: 'collection_status', value: 'Received'}).appendTo(pending);
        pending.submit();
    });
});
