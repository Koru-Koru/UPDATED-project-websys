$(function () {
    'use strict';

    $('#start_date').on('change', function () {
        $('#end_date').attr('min', this.value);
    });
    $('#end_date').on('change', function () {
        $('#start_date').attr('max', this.value);
    });

    $(window).on('beforeprint', function () {
        $('canvas').each(function () {
            const chart = Chart.getChart(this);
            if (chart) chart.update('none');
        });
    });

    const $report = $('#analytics-filter');
    if ($report.attr('data-export-pdf') === '1') {
        $(window).on('load', function () {
            document.title = 'Phbestcoffee Report ' + $('#start_date').val() + ' to ' + $('#end_date').val();
            window.setTimeout(function () { window.print(); }, 800);
        });
    }
});
