$(function () {
    'use strict';

    $('#redeem-counter-modal').on('show.bs.modal', function (event) {
        const $trigger = $(event.relatedTarget);
        const name = $trigger.attr('data-reward') || 'merchandise reward';
        $('#counter-reward-name').text(name);
        $('#counter-reward-points').text($trigger.attr('data-points'));
        $('#redemption-reward-id').val($trigger.attr('data-reward-id'));
        $('#counter-reward-image').attr('src', $trigger.attr('data-image')).attr('alt', name);
        $('#redeem-counter-title').text('Confirm your reward');
        $('#accept-reward').prop('checked', false);
        $('#reward-accept-btn').prop('disabled', true).removeClass('d-none');
    });
    $('#accept-reward').on('change', function () {
        $('#reward-accept-btn').prop('disabled', !this.checked);
    });
    $('#merchandise-redemption-form').on('submit', function (event) {
        if (!$('#accept-reward').is(':checked')) {
            event.preventDefault();
            return;
        }
        $('#reward-accept-btn').prop('disabled', true).text('Redeeming…');
    });
});
