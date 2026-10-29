$(function () {
    'use strict';

    $('#existing-product').on('change', function () {
        if (this.value) window.location.href = 'products.php?edit=' + encodeURIComponent(this.value);
    });
    const $form = $('#bulk-stock-form');
    if (!$form.length) return;
    const $all = $('#select-all-products');
    const $boxes = $('.product-select');
    function sync() {
        const count = $boxes.filter(':checked').length;
        $all.prop('checked', count > 0 && count === $boxes.length)
            .prop('indeterminate', count > 0 && count < $boxes.length);
        $('#bulk-stock-submit').prop('disabled', count === 0);
        $('#selected-count').text(count + ' product' + (count === 1 ? '' : 's') + ' selected');
    }
    $all.on('change', function () {
        $boxes.prop('checked', this.checked);
        sync();
    });
    $boxes.on('change', sync);
    $form.on('submit', function (event) {
        const count = $boxes.filter(':checked').length;
        const stock = $('#bulk-stock').val().trim() || 'unlimited';
        if (!count || !confirm('Set stock to ' + stock + ' for ' + count + ' selected product(s)?')) event.preventDefault();
    });
    sync();
});
